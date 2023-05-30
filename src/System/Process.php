<?php

namespace Eibly\System;

/**
 * @author Kleber Holtz (kleber.holtz@eibly.com)
 * @version 1.0.0
 * @license MIT
 * 
 * @method static prepare(string $command)
 * @method static background(bool $background)
 * @method static run(string $command)
 */
final class Process
{
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const EXITED = 255;
    public const TIMEOUT = 254;

    /**
     * @var string
     */
    protected const SPLIT_CODE = '_!r3sult@c0d3!_:';

    /**
     * @var bool
     */
    private static bool $success = false;

    /**
     * @var ?string
     */
    private static ?array $output = null;

    /**
     * @var ?string
     */
    private static bool $background = false;

    /**
     * @var ?string
     */
    private static ?string $command = null;

    /**
     * @var ?string
     */
    private static int $code = 0;

    /**
     * @var ?string
     */
    private static ?int $timeout = null;



    /**
     * Prepare the command to be run.
     * 
     * @param string $command
     * 
     * @return void
     */
    public static function prepare(string $command): void
    {
        self::$command = \escapeshellcmd($command);
    }

    /**
     * Determine if the command should be run in the background.
     * * If you need this command to be given asynchronously
     * 
     * @param bool $background
     * 
     * @return void
     */
    public static function background(bool $background): void
    {
        self::$background = $background;
    }

    /**
     * Determine the timeout of the command.
     * 
     * * Set the timeout in seconds.
     * * Define as null to disable the timeout.
     * 
     * @param ?int $timeout
     * 
     * @return void
     */
    public static function timeout(?int $timeout): void
    {
        self::$timeout = $timeout;
    }

    /**
     * Run the command.
     * 
     * @param string $command
     * 
     * @return void
     */
    public static function run(string $command): void

    {
        if (self::$command === null) {
            self::prepare($command);
        }

        return match (self::$background) {
            true => self::runBackground(),
            false => self::runForeground(),
            default => throw new \Exception('Error Processing Request', 1),
        };
    }

    /**
     * Run the command in the background.
     * 
     * @return void
     */
    private static function runBackground(): void
    {
        $output = \shell_exec(self::$command . '; echo ' . self::SPLIT_CODE . '$?');

        if ($output !== false) {
            $output = \explode(self::SPLIT_CODE, $output);
            $output = \array_filter(\array_map('trim', \explode("\n", $output[0])));
            $code = \intval($output[1]);
        } else {
            $output = null;
            $code = self::EXITED;
        }

        self::$success = $code === self::SUCCESS;
        self::$output = $output;
        self::$command = null;
    }

    /**
     * Run the command in the foreground.
     * 
     * @return void
     */
    private static function runForeground(): void
    {
        $command = self::$command;
        $output = null;
        $code = self::EXITED;

        if (self::$timeout !== null) {
            pcntl_signal(SIGALRM, function () use (&$code) {
                $code = self::TIMEOUT;
            });

            pcntl_alarm(self::$timeout);
        }

        if (exec($command, $output, $code)) {
            $output = array_filter(array_map('trim', $output));
        } else {
            $output = null;
            $code = self::EXITED;
        }

        if (self::$timeout !== null) {
            pcntl_alarm(0);
        }

        self::$success = $code === self::SUCCESS;
        self::$code = intval($code);
        self::$output = $output;
        self::$command = null;
    }

    /**
     * Determine if the command failed.
     * 
     * @return bool
     */
    public static function isError(): bool
    {
        return self::$success === false;
    }

    /**
     * Determine if the command was successful.
     * 
     * @return bool
     */
    public static function isSuccess(): bool
    {
        return self::$success;
    }

    /**
     * Get the output of the command.
     * 
     * @return array|null
     */
    public static function getOutput(): ?array
    {
        return self::$output;
    }

    /**
     * Get the output of the command as a string.
     * 
     * @return ?string
     */
    public static function getOutputAsString(): ?string
    {
        return self::$output === null ? null : \implode("\n", (array) self::$output);
    }

    /**
     * Get the exit code of the command.
     * 
     * @return int
     */
    public static function getCode(): int
    {
        return self::$code;
    }
}
