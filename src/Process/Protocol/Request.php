<?php

namespace lucatume\WPBrowser\Process\Protocol;

use Codeception\Exception\ConfigurationException;
use Opis\Closure\SerializableClosure;

class Request
{
    private Control $control;

    /**
     * @param array{autoloadFile: string, requireFiles: string[], cwd: string|false, codeceptionRootDir: string ,codeceptionConfig: array<string, mixed>, composerAutoloadPath: string|null, composerBinDir: string|null} $controlArray
     * @throws ConfigurationException
     */
    public function __construct(array $controlArray, private SerializableClosure $serializableClosure)
    {
        $this->control = new Control($controlArray);
    }

    public function getPayload(): string
    {
        return Parser::encode([$this->control->toArray(), $this->serializableClosure]);
    }

    public static function fromPayload(string $payload): self
    {
        // Decode only the control now to decode the rest when auto-loading is working.
        [$controlArray] = Parser::decode($payload, 0, 1);

        $control = new Control($controlArray);
        $control->apply();

        [$serializableClosure] = Parser::decode($payload, 1, 1);

        return new self($controlArray, $serializableClosure);
    }

    public function getSerializableClosure(): SerializableClosure
    {
        return $this->serializableClosure;
    }

    public function getControl(): Control
    {
        return clone $this->control;
    }
}
