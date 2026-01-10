<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class LocaleRedirectListener
{
    private UrlGeneratorInterface $urlGenerator;
    private string $defaultLocale;
    public function __construct(UrlGeneratorInterface $urlGenerator, string $defaultLocale) {
        $this->urlGenerator = $urlGenerator;
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->has('_locale')) {
            return;
        }
        if ($request->getPathInfo() !== '/') {
            return;
        }
        $url = $this->urlGenerator->generate('homepage', ['_locale' => $this->defaultLocale]);
        $response = new RedirectResponse($url, 302);
        $event->setResponse($response);
    }
}