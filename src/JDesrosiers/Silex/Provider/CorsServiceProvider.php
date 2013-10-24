<?php

namespace JDesrosiers\Silex\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The CORS service provider provides a `cors` service that a can be included in your project as application middleware.
 */
class CorsServiceProvider implements ServiceProviderInterface
{
    /**
     * Add OPTIONS method support for all routes
     *
     * @param Application $app
     */
    public function boot(Application $app)
    {
        $app->flush();

        $allow = array();
        foreach ($app["routes"] as $route) {
            $path = $route->getPath();
            if (!array_key_exists($path, $allow)) {
                $allow[$path] = array("methods" => array(), "requirements" => array());
            }
            $allow[$path]["methods"] = array_merge($allow[$path]["methods"], $route->getMethods());
            $allow[$path]["requirements"] = array_merge($allow[$path]["requirements"], $route->getRequirements());
        }

        foreach ($allow as $path => $routeDetails) {
            $methods = $routeDetails["methods"];
            $controller = $app->match(
                $path,
                function () use ($methods) {
                    return new Response("", 204, array("Allow" => implode(",", $methods)));
                }
            )->method('OPTIONS');

            unset($routeDetails["requirements"]["_method"]);
            $controller->setRequirements($routeDetails["requirements"]);
        }
    }

    /**
     * Register the cors function and set defaults
     *
     * @param Application $app
     */
    public function register(Application $app)
    {
        $app["cors.allowOrigin"] = null; // Defaults to all
        $app["cors.allowMethods"] = null; // Defaults to all
        //$app["cors.allowHeaders"] = "*";
        $app["cors.maxAge"] = null;
        $app["cors.allowCredentials"] = false;
        $app["cors.exposeHeaders"] = null;

        $app["cors"] = $app->protect(
            function (Request $request, Response $response) use ($app) {
                if (!$request->headers->has("Origin")) {
                    // Not a CORS request
                    return;
                }

                if ($request->getMethod() === "OPTIONS" && $request->headers->has("Access-Control-Request-Method")) {
                    if (!$this->preflight($app, $request, $response)) {
                        return;
                    }
                } elseif (!is_null($app["cors.exposeHeaders"])) {
                    $response->headers->set("Access-Control-Expose-Headers", $app["cors.exposeHeaders"]);
                }

                $origin = $request->headers->get("Origin");
                if (is_null($app["cors.allowOrigin"])) {
                    $app["cors.allowOrigin"] = $origin;
                }

                $allowOrigin = in_array($origin, preg_split('/\s+/', $app["cors.allowOrigin"])) ? $origin : "null";
                $response->headers->set("Access-Control-Allow-Origin", $allowOrigin);

                if ($app["cors.allowCredentials"]) {
                    $response->headers->set("Access-Control-Allow-Credentials", "true");
                }
            }
        );
    }

    protected function preflight(Application $app, Request $request, Response $response)
    {
        $allow = $response->headers->get("Allow");
        $allowMethods = is_null($app["cors.allowMethods"]) ? $allow : $app["cors.allowMethods"];

        $requestMethod = $request->headers->get("Access-Control-Request-Method");
        if (!in_array($requestMethod, preg_split("/\s*,\s*/", $allowMethods))) {
            // Not a valid prefight request
            return false;
        }

        if ($request->headers->has("Access-Control-Request-Headers")) {
            // TODO: Allow cors.allowHeaders to be set and use it to validate the request
            $requestHeaders = $request->headers->get("Access-Control-Request-Headers");
            $response->headers->set("Access-Control-Allow-Headers", $requestHeaders);
        }

        $response->headers->set("Access-Control-Allow-Methods", $allowMethods);

        if (!is_null($app["cors.maxAge"])) {
            $response->headers->set("Access-Control-Max-Age", $app["cors.maxAge"]);
        }

        return true;
    }
}
