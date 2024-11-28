<?php

declare(strict_types=1);

namespace SosoSicsi\EventHandler;

use SosoSicsi\EventHandler\Exceptions\EventNotRegisteredException;

/**
 * Class Event
 *
 * A static event handler class that allows registering, removing, and executing events with support for global handlers
 * and event priorities.
 *
 * @package SosoSicsi\EventHandler
 */
class Event
{
	/**
	 * @var array List of registered events and their handlers.
	 */
	protected static array $events = [];

	/**
	 * @var array Global handlers to be executed before any event-specific handlers.
	 */
	protected static array $globalBeforeEvents = [];

	/**
	 * @var array Global handlers to be executed after any event-specific handlers.
	 */
	protected static array $globalAfterEvents = [];

	/**
	 * Registers a new event by name.
	 *
	 * @param string $event The name of the event.
	 */
	public static function register(string $event): void
	{
		if (!isset(self::$events[$event])) {
			self::$events[$event] = [];
		}
	}

	/**
	 * Adds a global handler to be executed before any event-specific handlers.
	 *
	 * @param callable $handler The callable to execute.
	 */
	public static function listenGlobalBefore(callable $handler): void
	{
		self::$globalBeforeEvents[] = $handler;
	}

	/**
	 * Adds a global handler to be executed after any event-specific handlers.
	 *
	 * @param callable $handler The callable to execute.
	 */
	public static function listenGlobalAfter(callable $handler): void
	{
		self::$globalAfterEvents[] = $handler;
	}

	/**
	 * Removes an event and all its handlers.
	 *
	 * @param string $event The name of the event.
	 */
	public static function remove(string $event): void
	{
		unset(self::$events[$event]);
	}

	/**
	 * Adds a handler to a specific event with an optional priority.
	 *
	 * @param string   $event    The name of the event.
	 * @param callable $handler  The callable to execute.
	 * @param int      $priority The priority of the handler (higher values are executed first).
	 * @throws EventNotRegisteredException If the event is not registered.
	 */
	public static function listen(string $event, callable $handler, int $priority = 0): void
	{
		if (!isset(self::$events[$event])) {
			throw new EventNotRegisteredException("Event [{$event}] is not registered!");
		}

		self::$events[$event][] = ['handler' => $handler, 'priority' => $priority];
		usort(self::$events[$event], fn($a, $b) => $b['priority'] <=> $a['priority']);
	}

	/**
	 * Executes all handlers for a specific event, including global handlers.
	 *
	 * @param string $event The name of the event.
	 * @param mixed  ...$args Arguments to pass to the handlers.
	 * @return array The results from all executed handlers.
	 * @throws EventNotRegisteredException If the event is not registered.
	 */
	public static function use(string $event, ...$args): array
	{
		$results = [];

		foreach (self::$globalBeforeEvents as $index => $handler) {
			$results["globalBeforeEvent"][$index] = call_user_func_array($handler, [$event, ...$args]);
		}

		if (isset(self::$events[$event])) {
			foreach (self::$events[$event] as $entry) {
				$results[] = call_user_func_array($entry['handler'], $args);
			}
		} else {
			throw new EventNotRegisteredException("Event [{$event}] is not registered!");
		}

		foreach (self::$globalAfterEvents as $index => $handler) {
			$results["globalAfterEvent"][$index] = call_user_func_array($handler, [$event, ...$args]);
		}

		return $results;
	}

	/**
	 * Removes a specific handler from an event.
	 *
	 * @param string   $event   The name of the event.
	 * @param callable $handler The handler to remove.
	 */
	public static function removeListener(string $event, callable $handler): void
	{
		if (isset(self::$events[$event])) {
			self::$events[$event] = array_filter(
				self::$events[$event],
				fn($entry) => $entry['handler'] !== $handler
			);
		}
	}

	/**
	 * Adds a one-time handler to a specific event.
	 *
	 * @param string   $event   The name of the event.
	 * @param callable $handler The callable to execute.
	 */
	public static function once(string $event, callable $handler): void
	{
		$wrapper = function (...$args) use ($event, $handler, &$wrapper) {
			self::removeListener($event, $wrapper);
			return $handler(...$args);
		};

		self::listen($event, $wrapper);
	}

	/**
	 * Registers an event using its class name.
	 *
	 * @param object $event The event object.
	 */
	public static function registerEvent(object $event): void
	{
		$eventClass = get_class($event);
		if (!isset(self::$events[$eventClass])) {
			self::$events[$eventClass] = [];
		}
	}

	/**
	 * Executes all handlers for a specific event object.
	 *
	 * @param object $event The event object.
	 * @return array The results from all executed handlers.
	 */
	public static function useEvent(object $event): array
	{
		$eventClass = get_class($event);
		return self::use($eventClass, $event);
	}

	/**
	 * Gets a list of all registered events.
	 *
	 * @return array The names of all registered events.
	 */
	public static function getRegisteredEvents(): array
	{
		return array_keys(self::$events);
	}

	/**
	 * Gets all handlers registered for a specific event.
	 *
	 * @param string $event The name of the event.
	 * @return array The list of handlers for the event.
	 * @throws EventNotRegisteredException If the event is not registered.
	 */
	public static function getListeners(string $event): array
	{
		if (!isset(self::$events[$event])) {
			throw new EventNotRegisteredException("Event [{$event}] is not registered! Available events: " . implode(', ', self::getRegisteredEvents()));
		}

		return self::$events[$event];
	}
}
