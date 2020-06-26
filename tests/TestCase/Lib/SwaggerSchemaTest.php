<?php

namespace SwaggerBake\Test\TestCase\Lib;

use Cake\Routing\Router;
use Cake\Routing\RouteBuilder;
use Cake\TestSuite\TestCase;
use SwaggerBake\Lib\AnnotationLoader;
use SwaggerBake\Lib\CakeModel;
use SwaggerBake\Lib\CakeRoute;
use SwaggerBake\Lib\Configuration;
use SwaggerBake\Lib\Swagger;

class SwaggerSchemaTest extends TestCase
{
    public $fixtures = [
        'plugin.SwaggerBake.Employees',
    ];

    private $router;

    public function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $router = new Router();
        $router::scope('/api', function (RouteBuilder $builder) {
            $builder->setExtensions(['json']);
            $builder->resources('Employees');
            $builder->resources('EmployeeSalaries');
        });
        $this->router = $router;

        $this->config = new Configuration([
            'prefix' => '/api',
            'yml' => '/config/swagger-with-existing.yml',
            'json' => '/webroot/swagger.json',
            'webPath' => '/swagger.json',
            'hotReload' => false,
            'exceptionSchema' => 'Exception',
            'requestAccepts' => ['application/x-www-form-urlencoded'],
            'responseContentTypes' => ['application/json'],
            'namespaces' => [
                'controllers' => ['\SwaggerBakeTest\App\\'],
                'entities' => ['\SwaggerBakeTest\App\\'],
                'tables' => ['\SwaggerBakeTest\App\\'],
            ]
        ], SWAGGER_BAKE_TEST_APP);

        AnnotationLoader::load();
    }

    public function testEmployeeTableProperties()
    {
        $cakeRoute = new CakeRoute($this->router, $this->config);

        $swagger = new Swagger(new CakeModel($cakeRoute, $this->config));

        $arr = json_decode($swagger->toString(), true);

        $this->assertArrayHasKey('Employee', $arr['components']['schemas']);
        $employee = $arr['components']['schemas']['Employee'];
        
        $this->assertArrayHasKey('birth_date', $employee['properties']);

        $this->assertTrue($employee['properties']['id']['readOnly']);
        $this->assertEquals('integer', $employee['properties']['id']['type']);
    }

    public function testYmlSchemaTakesPrecedence()
    {
        $cakeRoute = new CakeRoute($this->router, $this->config);

        $swagger = new Swagger(new CakeModel($cakeRoute, $this->config));

        $arr = json_decode($swagger->toString(), true);

        $this->assertArrayHasKey('EmployeeSalaries', $arr['components']['schemas']);
        $employee = $arr['components']['schemas']['EmployeeSalaries'];

        $this->assertEquals('Test YML schema cannot be overwritten', $employee['description']);
    }
}