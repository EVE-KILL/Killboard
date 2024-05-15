<?php

namespace EK\Http\Twig;

use EK\Config\Config;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Twig
{
    protected Environment $twig;

    public function __construct(
        protected Config $config
    )
    {
        $templateDirectory = BASE_DIR . '/templates/';
        $loader = new FilesystemLoader($templateDirectory);
        $this->twig = new Environment($loader, [
            'cache' => BASE_DIR . '/cache/twig',
            'debug' => $this->config->get('twig/debug', false),
            'auto_reload' => $this->config->get('twig/autoReload', true),
            'strict_variables' => $this->config->get('twig/strictVariables', false),
            'optimizations' => $this->config->get('twig/optimizations', -1),
        ]);
    }

    public function render(string $templatePath, array $data = []): string
    {
        if (pathinfo($templatePath, PATHINFO_EXTENSION) !== 'twig') {
            throw new \RuntimeException('Error, twig templates need to end in .twig');
        }

        return $this->twig->render($templatePath, $data);
    }

    public function getTwig(): Environment
    {
        return $this->twig;
    }
}
