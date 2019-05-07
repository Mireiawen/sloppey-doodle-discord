<?php
declare(strict_types = 1);

namespace Mireiawen\Reader;

use ICal\ICal;

/**
 * Class Reader
 *
 * @package Mireiawen\Reader
 */
class Reader
{
	/**
	 * The ICal event feed
	 *
	 * @var ICal
	 */
	protected $ical;
	
	/**
	 * Construct the reader, this just sets the ICal feed
	 *
	 * @param ICal $feed
	 */
	public function __construct(ICal $feed)
	{
		$this->ical = $feed;
	}
	
	/**
	 * Get the events from the feed
	 *
	 * @param string|null $interval
	 *    The interval from where to get the events, NULL for all future events
	 *
	 * @return iterable|Event
	 * @throws \Exception
	 */
	public function GetEvents(?string $interval = NULL) : iterable
	{
		// Get all events since interval is not set
		if ($interval === NULL)
		{
			$events = $this->ical->events();
		}
		
		// Get events only for the interval
		else
		{
			$events = $this->ical->eventsFromInterval($interval);
		}
		
		// Turn the data into Event objects
		foreach ($events as $event)
		{
			yield new Event($event);
		}
	}
}
