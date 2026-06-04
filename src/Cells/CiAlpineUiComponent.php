<?php

declare(strict_types=1);

namespace Rakoitde\CiAlpineUI\Cells;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Exceptions\LogicException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\View\Cells\Cell;
use ReflectionClass;

/**
 * Base class for all CiAlpineUI components.
 *
 * Extend this class to create Alpine.js-powered CI4 Cell components.
 * Public properties are automatically serialized as x-data for Alpine.js.
 */
class CiAlpineUiComponent extends Cell
{
    use ResponseTrait;

    protected ResponseInterface $response;

    /**
     * @var bool Whether the action response should return full rendered HTML.
     */
    protected bool $returnAsHtml = true;

    /**
     * @var list<string>|null Property filter applied when returning JSON.
     */
    protected ?array $propertiesOnly = null;

    /**
     * Switches the action response to JSON mode.
     *
     * @param list<string>|null $propertiesOnly Limit response to these public property names.
     */
    protected function asJson(?array $propertiesOnly = null): self
    {
        if ($propertiesOnly) {
            $this->propertiesOnly = $propertiesOnly;
        }
        $this->returnAsHtml = false;

        return $this;
    }

    /**
     * Switches the action response to HTML mode (default).
     *
     * @param list<string>|null $propertiesOnly Unused in HTML mode; kept for API symmetry.
     */
    protected function asHtml(?array $propertiesOnly = null): self
    {
        if ($propertiesOnly) {
            $this->propertiesOnly = $propertiesOnly;
        }
        $this->returnAsHtml = true;

        return $this;
    }

    /**
     * Returns whether this component will respond with HTML after an action call.
     */
    public function returnAsHtml(): bool
    {
        return $this->returnAsHtml;
    }

    /**
     * Returns the Alpine.js x-data attribute string with all public properties as JSON.
     */
    public function getXDataTag(): string
    {
        return 'x-data="' . str_replace('"', "'", json_encode($this->getPublicProperties())) . '"';
    }

    /**
     * Returns the serialized public properties as a JSON string, safe for HTML embedding.
     */
    public function getComponentProperties(): string
    {
        return str_replace(
            '"',
            "'",
            json_encode(
                $this->getPublicProperties(),
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE,
            ),
        );
    }

    /**
     * Returns JavaScript lines mapping each public property to config values.
     * Used inside the generated Alpine.js component data function.
     */
    public function getDataProperties(): string
    {
        $properties = '';

        foreach (array_keys($this->getPublicProperties()) as $property) {
            $properties .= "\t\t\t" . $property . ': config.' . $property . ' ?? null,' . PHP_EOL;
        }

        return $properties;
    }

    /**
     * Returns the Alpine.js x-component attribute string with the (optionally encrypted) class name.
     */
    public function getXComponentTag(): string
    {
        $component = $this->encryptString(\str_replace('App\Cells\\', '', static::class));

        return 'x-component="' . $component . '"';
    }

    /**
     * Returns both Alpine.js attribute strings (x-data and x-component) for use in a view's root element.
     *
     * Usage: <div <?= $this->getXTags() ?>>
     */
    public function getXTags(): string
    {
        return $this->getXDataTag() . ' ' . $this->getXComponentTag();
    }

    /**
     * Returns the component's public properties, filtered by $propertiesOnly when set.
     *
     * @return array<string, mixed>
     */
    public function getOnlyPublicProperties(): array
    {
        if (! isset($this->propertiesOnly)) {
            return $this->getPublicProperties();
        }

        $publicProperties = [];

        foreach ($this->getPublicProperties() as $property => $value) {
            if (in_array($property, $this->propertiesOnly, true)) {
                $publicProperties[$property] = $value;
            }
        }

        return $publicProperties;
    }

    /**
     * Encrypts a string using the CI4 encrypter service when encryption is enabled.
     */
    protected function encryptString(?string $value): ?string
    {
        if (config('CiAlpineUI')->encrypt === false) {
            return $value;
        }

        return base64_encode(service('encrypter')->encrypt($value));
    }

    /**
     * Decrypts a string using the CI4 encrypter service when encryption is enabled.
     */
    protected function decryptString(?string $string): ?string
    {
        if (null === $string) {
            return null;
        }
        if (config('CiAlpineUI')->encrypt === false) {
            return $string;
        }

        return service('encrypter')->decrypt(base64_decode($string, true));
    }

    /**
     * Responsible for converting the view into HTML.
     * Expected to be overridden by the child class
     * in many occasions, but not all.
     */
    public function render(): string
    {
        if (! function_exists('decamelize')) {
            helper('inflector');
        }

        return $this->cellview($this->view);
    }

    /**
     * Actually renders the view, and returns the HTML.
     * In order to provide access to public properties and methods
     * from within the view, this method extracts $data into the
     * current scope and captures the output buffer instead of
     * relying on the view service.
     *
     * @param array<string, mixed> $data
     *
     * @throws LogicException
     */
    final protected function cellview(?string $view, array $data = []): string
    {
        $properties = $this->getPublicProperties();
        $properties = $this->includeComputedProperties($properties);
        $properties = array_merge($properties, $data);

        $view = (string) $view;

        $class       = new ReflectionClass(static::class);
        $staticClass = static::class;
        $class       = new ReflectionClass(new $staticClass());

        $parents   = [];
        $parents[] = $class->getFileName();

        while ($parent = $class->getParentClass()) {
            if ($parent->getName() !== 'CodeIgniter\View\Cells\Cell') {
                $parents[] = $parent->getFileName();
            }
            $class = $parent;
        }

        if ($view === '') {
            $viewName  = decamelize(class_basename(static::class));
            $directory = dirname((new ReflectionClass($this))->getFileName()) . DIRECTORY_SEPARATOR;

            $possibleView1 = $directory . substr($viewName, 0, (int) strrpos($viewName, '_cell')) . '.php';
            $possibleView2 = $directory . $viewName . '.php';
        }

        if ($view !== '' && ! is_file($view)) {
            $directory = dirname((new ReflectionClass($this))->getFileName()) . DIRECTORY_SEPARATOR;

            $view = $directory . $view . '.php';
        }

        $candidateViews = array_filter(
            [$view, $possibleView1 ?? '', $possibleView2 ?? ''],
            static fn (string $path): bool => $path !== '' && is_file($path),
        );

        if ($candidateViews === []) {
            throw new LogicException(sprintf(
                'Cannot locate the view file for the "%s" cell.',
                static::class,
            ));
        }

        $foundView = current($candidateViews);

        return (function () use ($properties, $foundView): string {
            extract($properties);
            ob_start();
            include $foundView;

            return ob_get_clean();
        })();
    }

    /**
     * Allows the developer to define computed properties
     * as methods with `get` prefixed to the protected/private property name.
     *
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    private function includeComputedProperties(array $properties): array
    {
        $reservedProperties = ['data', 'view'];
        $privateProperties  = $this->getNonPublicProperties();

        foreach ($privateProperties as $property) {
            $name = $property->getName();

            // don't include any methods in the base class
            if (in_array($name, $reservedProperties, true)) {
                continue;
            }

            $computedMethod = 'get' . ucfirst($name) . 'Property';

            if (method_exists($this, $computedMethod)) {
                $properties[$name] = $this->{$computedMethod}();
            }
        }

        return $properties;
    }

    public function __construct()
    {
        $this->response = response();
    }
}
