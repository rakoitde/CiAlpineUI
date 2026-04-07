<?php

namespace Rakoitde\CiAlpineUI\Cells;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\View\Cells\Cell;
use ReflectionClass;
use CodeIgniter\Exceptions\LogicException;

class CiAlpineUiComponent extends Cell
{
    use ResponseTrait;

    protected ResponseInterface $response;

    protected bool $returnAsHtml     = true;
    
    protected ?array $propertiesOnly = null;
    
    protected bool $encrypt = true;

    protected function asJson(?array $propertiesOnly = null): self
    {
        if ($propertiesOnly) {
            $this->propertiesOnly = $propertiesOnly;
        }
        $this->returnAsHtml = false;

        return $this;
    }

    protected function asHtml(?array $propertiesOnly = null): self
    {
        if ($propertiesOnly) {
            $this->propertiesOnly = $propertiesOnly;
        }
        $this->returnAsHtml = true;

        return $this;
    }

    public function returnAsHtml(): bool
    {
        return $this->returnAsHtml;
    }

    public function getXDataTag()
    {
        return 'x-data="' . str_replace('"', "'", json_encode($this->getPublicProperties())) . '"';
    }

    public function getComponentProperties()
    {
        return str_replace('"', "'", 
            json_encode(
                $this->getPublicProperties(),
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE));
    }

    public function getDataProperties()
    {
        $properties = '';

        foreach ($this->getPublicProperties() as $property => $value) {
            $properties .= "\t\t\t" . $property . ': config.' . $property . ' ?? null,' . PHP_EOL;
        }

        return $properties;
    }

    public function getXComponentTag()
    {
        $component = $this->encryptString(\str_replace('App\Cells\\', '', static::class));
        return 'x-component="' . $component . '"';
    }

    public function getXTags()
    {
        return $this->getXDataTag() . ' ' . $this->getXComponentTag();
    }

    public function getOnlyPublicProperties()
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

    protected function encryptString(?string $value): ?string
    {
        if (!$value || !$this->encrypt) return $value;

        return base64_encode(service('encrypter')->encrypt($value));
    }

    protected function decryptString(null|string $string): ?string
    {
        if (null==$string) return null;

        return $this->encrypt
            ? service('encrypter')->decrypt(base64_decode($string))
            : $string;
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
     * @throws LogicException
     */
    final protected function cellview(?string $view, array $data = []): string
    {
        $properties = $this->getPublicProperties();
        $properties = $this->includeComputedProperties($properties);
        $properties = array_merge($properties, $data);

        $view = (string) $view;

        $class = new ReflectionClass(get_class($this));
        $staticClass = static::class;
        $class = new ReflectionClass(new $staticClass());
        
        $parents = [];
        $parents[] = $class->getFileName();
        
        while ($parent = $class->getParentClass()) {
            if ($parent->getName() !== 'CodeIgniter\View\Cells\Cell') {
                $file = (new \CodeIgniter\Files\File($parent->getFileName()))->getPath();
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
