<?php

declare(strict_types=1);

namespace Rakoitde\CiAlpineUI\Controllers;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use Config\App;
use Exception;
use Rakoitde\CiAlpineUI\Cells\CiAlpineUiComponentTestCell;

/**
 * @internal
 */
final class CiAlpineUiControllerTest extends CIUnitTestCase
{
    use ControllerTestTrait;

    public function testNoComponentFound(): void
    {
        $request = new IncomingRequest(
            new App(),
            new URI('http://example.com/component'),
            null,
            new UserAgent(),
        );

        $result = $this->withRequest($request)
            ->controller(CiAlpineUiController::class)
            ->execute('index');
        $json = \json_decode($result->getJSON());

        $this->assertSame('No component found', $json->messages->error);
        $result->assertStatus(400);
    }

    public function testNoActionSend(): void
    {
        $request = new IncomingRequest(
            new App(),
            new URI('http://example.com/component'),
            null,
            new UserAgent(),
        );

        $body = json_encode([
            'component' => ['name' => CiAlpineUiComponentTestCell::class],
        ]);

        $result = $this->withRequest($request)
            ->withBody($body)
            ->controller(CiAlpineUiController::class)
            ->execute('index');
        $json = \json_decode($result->getJSON());

        $this->assertSame('no Action send', $json->messages->error);
        $result->assertStatus(400);
    }

    public function testActionNotFound(): void
    {
        $request = new IncomingRequest(
            new App(),
            new URI('http://example.com/component'),
            null,
            new UserAgent(),
        );

        $body = json_encode([
            'component' => ['name' => CiAlpineUiComponentTestCell::class],
            'request'   => ['action' => 'notFound'],
        ]);

        $result = $this->withRequest($request)
            ->withBody($body)
            ->controller(CiAlpineUiController::class)
            ->execute('index');
        $json = \json_decode($result->getJSON());

        $this->assertSame("Method 'notFound' not found in component 'Rakoitde\\CiAlpineUI\\Cells\\CiAlpineUiComponentTestCell'", $json->messages->error);
        $result->assertStatus(400);
    }

    public function testTestMethodNoPermission(): void
    {
        $request = new IncomingRequest(
            new App(),
            new URI('http://example.com/component'),
            null,
            new UserAgent(),
        );

        $body = json_encode([
            'component' => ['name' => CiAlpineUiComponentTestCell::class],
            'request'   => ['action' => 'testNoPermission'],
        ]);

        $result = $this->withRequest($request)
            ->withBody($body)
            ->controller(CiAlpineUiController::class)
            ->execute('index');

        $json = \json_decode($result->getJSON());

        $this->assertSame("You have no permission to access 'testNoPermission' in component 'Rakoitde\\CiAlpineUI\\Cells\\CiAlpineUiComponentTestCell'", $json->messages->error);
        $result->assertStatus(403);
    }

    public function testTestMethodHtmlResult(): void
    {
        $request = new IncomingRequest(
            new App(),
            new URI('http://example.com/component'),
            null,
            new UserAgent(),
        );

        $body = json_encode([
            'component' => ['name' => CiAlpineUiComponentTestCell::class],
            'request'   => ['action' => 'testAsHtml'],
        ]);

        $result = $this->withRequest($request)
            ->withBody($body)
            ->controller(CiAlpineUiController::class)
            ->execute('index');

        $json = \json_decode($result->getJSON());

        $this->assertSame('<div x-data="{\'canAccess\':false,\'boolVal\':false,\'intVal\':0,\'floatVal\':0,\'stringVal\':\'\',\'arrayVal\':[]}" x-component="Rakoitde\CiAlpineUI\Cells\CiAlpineUiComponentTestCell"></div>', $json->html);
        $result->assertStatus(200);
    }

    public function testTestMethodJsonResult(): void
    {
        $request = new IncomingRequest(
            new App(),
            new URI('http://example.com/component'),
            null,
            new UserAgent(),
        );

        $body = json_encode([
            'component' => ['name' => CiAlpineUiComponentTestCell::class],
            'request'   => ['action' => 'testAsJson'],
        ]);

        $result = $this->withRequest($request)
            ->withBody($body)
            ->controller(CiAlpineUiController::class)
            ->execute('index');

        $json = \json_decode($result->getJSON());

        $this->assertFalse($json->canAccess);
        $result->assertStatus(200);
    }

    public function testTestMethodJsonResultWithProperties(): void
    {
        $request = new IncomingRequest(
            new App(),
            new URI('http://example.com/component'),
            null,
            new UserAgent(),
        );

        $body = json_encode([
            'component' => ['name' => CiAlpineUiComponentTestCell::class],
            'request'   => ['action' => 'testAsJsonWithProperties'],
        ]);

        $result = $this->withRequest($request)
            ->withBody($body)
            ->controller(CiAlpineUiController::class)
            ->execute('index');

        $json = \json_decode($result->getJSON());

        $this->assertFalse($json->canAccess);
        $result->assertStatus(200);
    }

    public function testTestComponentClassNotExists(): void
    {
        $request = new IncomingRequest(
            new App(),
            new URI('http://example.com/component'),
            null,
            new UserAgent(),
        );

        $body = json_encode([
            'component' => ['name' => 'Rakoitde\CiAlpineUI\Cells\CiAlpineUiComponentNotExists'],
            'request'   => ['action' => 'testAsJsonWithProperties'],
        ]);

        $result = $this->withRequest($request)
            ->withBody($body)
            ->controller(CiAlpineUiController::class)
            ->execute('index');

        $json = \json_decode($result->getJSON());

        $this->assertSame('No component found', $json->messages->error);
        $result->assertStatus(400);
    }

    public function testTestComponentIsNotAnInstanceOfCiComponent(): void
    {
        $request = new IncomingRequest(
            new App(),
            new URI('http://example.com/component'),
            null,
            new UserAgent(),
        );

        $body = json_encode([
            'component' => ['name' => 'App\Controllers\Home'],
            'request'   => ['action' => 'testAsJsonWithProperties'],
        ]);

        $this->expectException(Exception::class);

        $this->withRequest($request)->withBody($body)->controller(CiAlpineUiController::class)->execute('index');
    }

    public function testTestDataFormat(): void
    {
        $body = [
            'component' => ['name' => CiAlpineUiComponentTestCell::class],
            'data'      => [
                'boolVal'   => true,
                'intVal'    => 1,
                'doubleVal' => 1.234,
                'floatVal'  => 1.123,
                'stringVal' => 'String',
                'arrayVal'  => ['Array'],
            ],
            'request' => ['action' => 'testAsHtml'],
        ];

        $ciAlpineUiComponent = new CiAlpineUiComponentTestCell();
        $ciAlpineUiComponent->render();

        $result = $this
            ->withBody(json_encode($body))
            ->controller(CiAlpineUiController::class)
            ->execute('index');

        $json = json_decode($result->response()->getJSON());

        $this->assertSame('<div x-data="{\'canAccess\':false,\'boolVal\':true,\'intVal\':1,\'floatVal\':1.123,\'stringVal\':\'String\',\'arrayVal\':[\'Array\']}" x-component="Rakoitde\CiAlpineUI\Cells\CiAlpineUiComponentTestCell"></div>', $json->html);
        $result->assertStatus(200);
    }

    public function testComponentCouldRender(): void
    {
        $ciAlpineUiComponent = new CiAlpineUiComponentTestCell();
        $this->assertSame('<div x-data="{\'canAccess\':false,\'boolVal\':false,\'intVal\':0,\'floatVal\':0,\'stringVal\':\'\',\'arrayVal\':[]}" x-component="Rakoitde\CiAlpineUI\Cells\CiAlpineUiComponentTestCell"></div>', $ciAlpineUiComponent->render());
    }
}
