<?php

namespace JDesrosiers\Silex\Provider;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function __invoke(Request $request, Response $response)
    {
        $response->headers->add($this->corsHeaders($request, $response->headers->get("Allow")));
    }

    private function corsHeaders(Request $request, $allow)
    {
        $headers = array();

        if (!$this->isCorsRequest($request)) {
            return array();
        }

        if ($this->isPreflightRequest($request)) {
            $allowedMethods = $this->allowedMethods($allow);
            $requestMethod = $request->headers->get("Access-Control-Request-Method");
            if (!in_array($requestMethod, preg_split("/\s*,\s*/", $allowedMethods))) {
                return array();
            }

            // TODO: Allow cors.allowHeaders to be set and use it to validate the request
            $headers["Access-Control-Allow-Headers"] = $request->headers->get("Access-Control-Request-Headers");
            $headers["Access-Control-Allow-Methods"] = $allowedMethods;
            $headers["Access-Control-Max-Age"] = $this->app["cors.maxAge"];
        } else {
            $headers["Access-Control-Expose-Headers"] = $this->app["cors.exposeHeaders"];
        }

        $headers["Access-Control-Allow-Origin"] = $this->allowOrigin($request);
        $headers["Access-Control-Allow-Credentials"] = $this->allowCredentials();

        return array_filter($headers);
    }

    private function isCorsRequest(Request $request)
    {
        return $request->headers->has("Origin");
    }

    private function isPreflightRequest(Request $request)
    {
        return $request->getMethod() === "OPTIONS" && $request->headers->has("Access-Control-Request-Method");
    }

    private function allowedMethods($allow)
    {
        return !is_null($this->app["cors.allowMethods"]) ? $this->app["cors.allowMethods"] : $allow;
    }

    private function allowOrigin(Request $request)
    {
        $origin = $request->headers->get("Origin");
        if ($this->app["cors.allowOrigin"] === "*") {
            $this->app["cors.allowOrigin"] = $origin;
        }

        $origins = preg_split('/\s+/', $this->app["cors.allowOrigin"]);
        foreach ($origins as $domain) {
            if (preg_match($this->domainToRegex($domain), $origin)) {
                return $origin;
            }
        }

        return "null";
    }

    private function domainToRegex($domain)
    {
        return "/^" . preg_replace("/^\\\\\*/", "[^.]*", preg_quote($domain)) . "$/";
    }

    private function allowCredentials()
    {
        return $this->app["cors.allowCredentials"] === true ? "true" : null;
    }
}
