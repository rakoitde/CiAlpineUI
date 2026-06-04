<?php

declare(strict_types=1);

namespace Rakoitde\CiAlpineUI\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use Exception;
use Override;
use Rakoitde\CiAlpineUI\Cells\CiAlpineUiComponent;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Handles all AJAX action requests from Alpine.js $cell magic.
 *
 * Receives a POST to /component, resolves and hydrates the component class,
 * validates the requested action, dispatches it, and returns HTML or JSON.
 */
class CiAlpineUiController extends ResourceController
{
    protected $component;

    /**
     * Entry point for all component action calls from the frontend.
     *
     * @return ResponseInterface
     */
    #[Override]
    public function index()
    {
        $request = array_merge(
            json_decode(json_encode(request()->getJson()), true) ?? [],
            json_decode(json_encode(request()->getGet()), true) ?? [],
        );

        $this->component = $this->getComponent($request);

        if (null === $this->component) {
            return $this->fail('No component found');
        }

        if (isset($request['data'])) {
            $this->component->fill($this->getData($request['data']));
        }

        if (! isset($request['request']['action'])) {
            return $this->fail('no Action send');
        }

        $action = $request['request']['action'];

        $viewCellClass = $this->component::class;
        if ($this->isNoPublicAction($action)) {
            return $this->fail("Method '{$action}' not found in component '{$viewCellClass}'");
        }

        if ($this->actionIsForbidden($action)) {
            return $this->failForbidden("You have no permission to access '{$action}' in component '{$viewCellClass}'");
        }

        $params = $this->getParameter($request);

        call_user_func_array([$this->component, $action], $params);

        if ($this->component->returnAsHtml()) {
            return $this->respond(['html' => $this->component->render()]);
        }

        return $this->respond($this->component->getOnlyPublicProperties());
    }

    /**
     * Resolves and instantiates the component class from the request payload.
     *
     * @param array<string, mixed> $request
     *
     * @throws Exception When the resolved class is not a CiAlpineUiComponent.
     */
    protected function getComponent(array $request): ?CiAlpineUiComponent
    {
        if (! isset($request['component'])) {
            return null;
        }

        $component = $this->decryptString($request['component']['name']);

        $viewCellClass = str_replace('/', '\\', $component);
        if (! class_exists($viewCellClass)) {
            $viewCellClass = 'App\Cells\\' . $viewCellClass;
        }

        if (class_exists($viewCellClass)) {
            $viewCellObject = new $viewCellClass();
            if (! ($viewCellObject instanceof CiAlpineUiComponent)) {
                throw new Exception($viewCellClass . ' is not an instanceof \Rakoitde\CiAlpineUI\Cells\CiAlpineUiComponent', 1);
            }

            return $viewCellObject;
        }

        return null;
    }

    /**
     * Decrypts the component name when encryption is enabled.
     */
    protected function decryptString(?string $value): ?string
    {
        if (config('CiAlpineUI')->encrypt === false) {
            return $value;
        }

        if (null === $value) {
            return $value;
        }

        return service('encrypter')->decrypt(base64_decode($value, true));
    }

    /**
     * Extracts action parameters from the request payload.
     *
     * @param array<string, mixed> $request
     *
     * @return list<mixed>
     */
    protected function getParameter(array $request): array
    {
        return $request['request']['params'] ?? [];
    }

    /**
     * Casts each incoming data value to the type declared on the corresponding public property.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function getData(array $data): array
    {
        $component = $this->component;

        foreach (array_keys($component->getOnlyPublicProperties()) as $publicProperty) {
            if (! isset($data[$publicProperty])) {
                continue;
            }

            $reflectionProperty     = new ReflectionProperty($component, $publicProperty);
            $reflectionPropertyType = $reflectionProperty->getType();
            $reflectionPropertyName = $reflectionPropertyType ? $reflectionPropertyType->getName() : null;

            $data[$publicProperty] = match ($reflectionPropertyName) {
                'bool'   => $data[$publicProperty] === 'false' ? false : (bool) ($data[$publicProperty]),
                'int'    => (int) ($data[$publicProperty]),
                'float'  => (float) ($data[$publicProperty]),
                'string' => (string) ($data[$publicProperty]),
                default  => $data[$publicProperty],
            };
        }

        return $data;
    }

    /**
     * Returns the names of all public methods on the component that may be called as actions.
     *
     * @return list<string>
     */
    protected function getPublicMethodNamesFromClass(): array
    {
        $class   = new ReflectionClass($this->component);
        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);

        $nonePublicActions = [
            'returnAsHtml',
            'getXDataTag',
            'getXComponentTag',
            'getXTags',
            '__construct',
            'getOnlyPublicProperties',
            'render',
            'setView',
            '__toString',
            'fill',
            'getPublicProperties',
            'getNonPublicProperties',
        ];

        return array_diff(array_column($methods, 'name'), $nonePublicActions);
    }

    /**
     * Returns true when the given action name is not a callable public method on the component.
     */
    protected function isNoPublicAction(string $action): bool
    {
        $publicMethodNames = $this->getPublicMethodNamesFromClass();

        return ! in_array($action, $publicMethodNames, true);
    }

    /**
     * Returns true when a canAccess* guard method exists and returns false.
     */
    protected function actionIsForbidden(string $action): bool
    {
        $method = 'canAccess' . ucfirst($action);

        if ($this->isNoPublicAction($method)) {
            return false;
        }

        return ! call_user_func_array([$this->component, $method], []);
    }
}
