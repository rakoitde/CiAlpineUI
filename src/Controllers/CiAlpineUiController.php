<?php

namespace Rakoitde\CiAlpineUI\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use Exception;
use Override;
use Rakoitde\CiAlpineUI\Cells\CiAlpineUiComponent;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class CiAlpineUiController extends ResourceController
{
    protected $component;

    protected bool $encrypt = true;

    /**
     * Return an array of resource objects, themselves in array format.
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

        // Check if action exists
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

    protected function getComponent($request)
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

    protected function decryptString(?string $value): ?string
    {
        if (!$value || !$this->encrypt) return $value;

        return service('encrypter')->decrypt(base64_decode($value));
    }

    protected function getParameter($request)
    {
        return $request['request']['params'] ?? [];
    }

    protected function getData($data)
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

    protected function isNoPublicAction($action): bool
    {
        $publicMethodNames = $this->getPublicMethodNamesFromClass();

        return ! in_array($action, $publicMethodNames, true);
    }

    protected function actionIsForbidden($action): bool
    {
        $method = 'canAccess' . ucfirst((string) $action);

        if ($this->isNoPublicAction($method)) {
            return false;
        }

        return ! call_user_func_array([$this->component, $method], []);
    }
}
