<?php

use Psr\Container\ContainerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

class Kernel
{
    protected RouteCollection|array $routes;
    protected FileLocator $configLocator;
    public ContainerInterface $container;

    public function __construct(
        protected string $env = 'dev',
        protected string $configDir = ROOT . '/config',
        protected string $cacheDir = ROOT . '/var/cache',
        protected string $templateDir = ROOT . '/templates')
    {
        $this->routes = new RouteCollection();
        $this->configLocator = new FileLocator($this->configDir);

        $this->loadContainer();
        $this->loadRoutes();
    }

    public function handle(?Request $request = null): Response
    {
        $request = $request ?? Request::createFromGlobals();
        $context = (new RequestContext())->fromRequest($request);

        // Populate request-dependent services
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->container->set(RequestContext::class, $context);
        $this->container->set(UrlGeneratorInterface::class, new UrlGenerator($this->routes, $context));
        $this->container->set(UrlHelper::class, new UrlHelper($requestStack, $context));
        $twig = $this->container->get(Environment::class);
        $twig->addGlobal('session', $this->container->get(Session::class));
        $twig->addGlobal('request', $request);

        if ($this->isProduction()) {
            $matcher = new CompiledUrlMatcher($this->routes, $context);
        } else {
            $matcher = new UrlMatcher($this->routes, $context);
        }

        try {
            $params = $matcher->matchRequest($request);
            $request->attributes->add($params);

            $action = $params['_action'] ?? 'invalidRoute';
            $controller = $this->container->get($params['_controller']);
            $response = $controller->$action($request);

            if (!$response) {
                return new Response();
            }

            return $response;
        } catch (ResourceNotFoundException $exception) {
            header('HTTP/1.0 404');
            exit;
        } catch (Throwable $throwable) {
            header('HTTP/1.0 500');
            echo (string) $throwable;
            exit;
        }
    }

    protected function loadRoutes()
    {
        // Load cached compiled routing map if it exists
        $cache = $this->cacheDir . '/routes.php';
        if ($this->isProduction() && file_exists($cache)) {
            $this->routes = require($cache);

            return;
        }

        // Create attribute-based route configuration loader
        $loader = new AnnotationDirectoryLoader(new FileLocator(), new CustomLoader());

        // Find attributes in all 2nd level namespaces containing "Controller"
        foreach (glob(ROOT . '/src/*/*Controller', GLOB_ONLYDIR) as $dir) {
            $this->routes->addCollection($loader->load($dir));
        }

        // Cache compiled routing map
        $dumper = new CompiledUrlMatcherDumper($this->routes);
        file_put_contents($cache, $dumper->dump());
    }

    protected function loadContainer()
    {
        // Load cached container if it exists
        $cache = $this->cacheDir . '/container.php';
        if ($this->isProduction() && file_exists($cache)) {
            require_once $cache;
            $this->container = new ProjectServiceContainer();

            return;
        }

        $this->container = new ContainerBuilder();

        // Setup Twig
        $this->container->setDefinition('template_loader', new Definition(FilesystemLoader::class, [$this->templateDir]));
        $twig = new Definition(Environment::class, [
            new Reference('template_loader'),
            [
                'cache' => $this->isProduction() ? $this->cacheDir . '/twig' : false,
                'debug' => !$this->isProduction(),
            ]
        ]);
        $twig->setPublic(true);
        $this->container->setDefinition(Environment::class, $twig);

        // Define request-dependent services
        $this->container->setDefinition(Session::class, (new Definition(Session::class))->setPublic(true));
        $this->container->setDefinition(RequestContext::class, (new Definition(RequestContext::class))->setSynthetic(true));
        $this->container->setDefinition(UrlGeneratorInterface::class, (new Definition(UrlGenerator::class))->setSynthetic(true));
        $this->container->setDefinition(UrlHelper::class, (new Definition(UrlHelper::class))->setSynthetic(true));

        // Load service configuration
        $loader = new YamlFileLoader($this->container, $this->configLocator);
        $loader->load('services.yaml');

        // Setup twig extensions
        foreach ($this->container->findTaggedServiceIds('twig.extension') as $id => $tags) {
            $twig->addMethodCall('addExtension', [new Reference($id)]);
        }

        // Compile the container
        $this->container->compile();

        // Cache compiled container
        file_put_contents($cache, (new PhpDumper($this->container))->dump());
    }

    public function isProduction()
    {
        return $this->env === 'prod';
    }
}

$env = getenv('APP_ENV') ?? 'dev';
$kernel = new Kernel($env);
$response = $kernel->handle();
$response->send();
