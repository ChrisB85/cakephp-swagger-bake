<?php


namespace SwaggerBake\Test\TestCase\Lib;


use Cake\Routing\Route\DashedRoute;
use Cake\Routing\Router;
use Cake\Routing\RouteBuilder;
use Cake\TestSuite\TestCase;

use SwaggerBake\Lib\Exception\SwaggerBakeRunTimeException;
use SwaggerBake\Lib\Model\ModelScanner;
use SwaggerBake\Lib\OpenApi\Path;
use SwaggerBake\Lib\Route\RouteScanner;
use SwaggerBake\Lib\Configuration;
use SwaggerBake\Lib\Swagger;
use SwaggerBake\Lib\Utility\FileUtility;

class SwaggerTest extends TestCase
{
    /**
     * @var string[]
     */
    public $fixtures = [
        'plugin.SwaggerBake.DepartmentEmployees',
        'plugin.SwaggerBake.Departments',
        'plugin.SwaggerBake.Employees',
    ];

    private Router $router;

    private array $config;

    public function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $router = new Router();
        $router::scope('/', function (RouteBuilder $builder) {
            $builder->setExtensions(['json']);
            $builder->resources('Employees', [
                'map' => [
                    'customGet' => [
                        'action' => 'customGet',
                        'method' => 'GET',
                        'path' => 'custom-get'
                    ],
                    'customPost' => [
                        'action' => 'customPost',
                        'method' => 'POST',
                        'path' => 'custom-post'
                    ]
                ]
            ]);
            $builder->resources('Departments', function (RouteBuilder $routes) {
                $routes->resources('DepartmentEmployees');
            });
        });
        $this->router = $router;

        $this->config = [
            'prefix' => '/',
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
        ];
    }

    public function test_get_mixing_static_yaml_and_dynamic_openapi(): void
    {
        $config = new Configuration($this->config, SWAGGER_BAKE_TEST_APP);
        $cakeRoute = new RouteScanner($this->router, $config);
        $openApi = (new Swagger(new ModelScanner($cakeRoute, $config), $config))->getArray();

        $this->assertArrayHasKey('/departments', $openApi['paths']);
        $this->assertArrayHasKey('/pets', $openApi['paths']);
        $this->assertArrayHasKey('Pets', $openApi['components']['schemas']);
    }

    public function test_get_array_from_bare_bones(): void
    {
        $vars = $this->config;
        $vars['yml'] = '/config/swagger-bare-bones.yml';
        $config = new Configuration($vars, SWAGGER_BAKE_TEST_APP);

        $cakeRoute = new RouteScanner($this->router, $config);

        $swagger = new Swagger(new ModelScanner($cakeRoute, $config), $config);
        $arr = json_decode($swagger->toString(), true);

        $this->assertArrayHasKey('/departments', $arr['paths']);
        $this->assertArrayHasKey('Department', $arr['components']['schemas']);
    }

    public function test_custom_json_options(): void
    {
        $vars = $this->config;
        $vars['jsonOptions'] = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $config = new Configuration($vars, SWAGGER_BAKE_TEST_APP);

        $cakeRoute = new RouteScanner($this->router, $config);

        $swagger = new Swagger(new ModelScanner($cakeRoute, $config), $config);
        $jsonString = $swagger->toString();

        $this->assertStringNotContainsString('"\/departments"', $jsonString);
        $this->assertStringContainsString('"/departments"', $jsonString);
        $this->assertStringNotContainsString("\n", $jsonString);

        $arr = json_decode($swagger->toString(), true);

        $this->assertArrayHasKey('/pets', $arr['paths']);
        $this->assertArrayHasKey('Pets', $arr['components']['schemas']);
    }

    public function test_http_put_on_edit_action(): void
    {
        $vars = $this->config;
        $vars['editActionMethods'] = ['PUT'];
        $config = new Configuration($vars, SWAGGER_BAKE_TEST_APP);

        $cakeRoute = new RouteScanner($this->router, $config);

        $swagger = new Swagger(new ModelScanner($cakeRoute, $config), $config);
        $openApi = $swagger->getArray();
        /** @var Path $path */
        $path = $openApi['paths']['/employees/{id}'];
        $this->assertArrayHasKey('put', $path->getOperations());
        $this->assertArrayNotHasKey('patch', $path->getOperations());
    }

    public function test_write_file_throws_exception_if_file_not_writable(): void
    {
        $vars = $this->config;
        $vars['yml'] = '/config/swagger-bare-bones.yml';
        $config = new Configuration($vars, SWAGGER_BAKE_TEST_APP);
        $routeScanner = new RouteScanner($this->router, $config);

        $mockFileUtility = $this->createMock(FileUtility::class);
        $mockFileUtility->expects($this->once())->method('isWritable')->willReturn(false);

        $this->expectException(SwaggerBakeRunTimeException::class);
        $swagger = new Swagger(new ModelScanner($routeScanner, $config), $config, $mockFileUtility);
        $swagger->writeFile('/anything.json');
        $this->assertStringContainsString('Output file is not writable', $this->getExpectedExceptionMessage());
    }

    public function test_write_file_throws_exception_if_file_write_fails(): void
    {
        $vars = $this->config;
        $vars['yml'] = '/config/swagger-bare-bones.yml';
        $config = new Configuration($vars, SWAGGER_BAKE_TEST_APP);
        $routeScanner = new RouteScanner($this->router, $config);

        $mockFileUtility = $this->createMock(FileUtility::class);
        $mockFileUtility->expects($this->once())->method('isWritable')->willReturn(true);
        $mockFileUtility->expects($this->once())->method('putContents')->willReturn(false);

        $this->expectException(SwaggerBakeRunTimeException::class);
        $swagger = new Swagger(new ModelScanner($routeScanner, $config), $config, $mockFileUtility);
        $swagger->writeFile('/anything.json');
        $this->assertStringContainsString(
            'Error encountered while writing swagger file',
            $this->getExpectedExceptionMessage()
        );
    }

    public function test_components_is_removed_if_empty(): void
    {
        $config = new Configuration($this->config, SWAGGER_BAKE_TEST_APP);
        $routeScanner = new RouteScanner($this->router, $config);
        $swagger = new Swagger(new ModelScanner($routeScanner, $config), $config);
        $array = $swagger->getArray();
        $array['components'] = [];
        $swagger->setArray($array);
        $this->assertArrayNotHasKey('components', $swagger->getArray());
    }
}