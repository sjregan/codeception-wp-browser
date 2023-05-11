<?php

namespace lucatume\WPBrowser\WordPress;

use Exception;
use lucatume\WPBrowser\Utils\Arr;
use lucatume\WPBrowser\Utils\ErrorHandling;
use lucatume\WPBrowser\Utils\Property;
use ReflectionException;
use WP_Error;
use function str_starts_with;

class WPDieException extends Exception
{
    /**
     * @param string|array<string,mixed>|int $args
     * @throws ReflectionException
     */
    public function __construct(string|WP_Error $message = '', string|int $title = '', string|array|int $args = [])
    {
        if ($message instanceof WP_Error) {
            $title = $message->get_error_data('title') ?: '';
            $exitCode = (int)$message->get_error_code();
            $message = $message->get_error_message();
        } else {
            $title = $title ?: '';
            $message = $message ?: '';
            $exitCode = $args['code'] ?? 1;
        }

        $message = strip_tags($title ? "$title - $message" : $message);

        parent::__construct($message, $exitCode, null);

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        $wpDieCallPos = Arr::searchWithCallback(static function (array $item): bool {
            return $item['function'] === 'wp_die';
        }, $trace);
        $serializableClosurePos = Arr::searchWithCallback(static function (array $item): bool {
            return isset($item['file']) && str_starts_with($item['file'], 'closure://');
        }, $trace);

        if (is_int($wpDieCallPos)) {
            if (is_int($serializableClosurePos)) {
                $trace = array_slice($trace, $wpDieCallPos, $serializableClosurePos - $wpDieCallPos);
            } else {
                $trace = array_slice($trace, $wpDieCallPos);
            }
        }

        $traceAsString1 = ErrorHandling::traceAsString(array_values($trace));
        $traceAsString = str_replace("\n", "\n\t\t", $traceAsString1);

        Property::setPrivateProperties($this, ['trace' => $trace, 'traceAsString' => $traceAsString]);
    }
}
