<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Apply security headers from config
        $this->applySecurityHeaders($response);

        return $response;
    }

    private function applySecurityHeaders(Response $response): void
    {
        $securityConfig = config('security.headers');
        $cspConfig = config('security.csp');

        // Basic security headers
        $response->headers->set('X-Frame-Options', $securityConfig['x_frame_options']);
        $response->headers->set('X-Content-Type-Options', $securityConfig['x_content_type_options']);
        $response->headers->set('X-XSS-Protection', $securityConfig['x_xss_protection']);
        $response->headers->set('Referrer-Policy', $securityConfig['referrer_policy']);

        // HSTS Header
        if ($securityConfig['hsts']['enabled']) {
            $hsts = "max-age={$securityConfig['hsts']['max_age']}";
            if ($securityConfig['hsts']['include_subdomains']) {
                $hsts .= '; includeSubDomains';
            }
            if ($securityConfig['hsts']['preload']) {
                $hsts .= '; preload';
            }
            $response->headers->set('Strict-Transport-Security', $hsts);
        }

        // CSP Header
        if ($cspConfig['enabled']) {
            $cspHeader = $this->buildCspHeader($cspConfig);
            $headerName = $cspConfig['report_only'] ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $response->headers->set($headerName, $cspHeader);
        }
    }

    private function buildCspHeader(array $cspConfig): string
    {
        $directives = [];

        foreach ($cspConfig['directives'] as $directive => $sources) {
            if (!empty($sources)) {
                $directives[] = $directive . ' ' . implode(' ', $sources);
            }
        }

        return implode('; ', $directives);
    }
}