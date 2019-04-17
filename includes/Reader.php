<?php
declare(strict_types = 1);

namespace Reader;

use ICal\ICal;

class Reader
{
	protected $ical;
	
	/**
	 * Reader constructor.
	 *
	 * @param ICal $feed
	 */
	public function __construct(ICal $feed)
	{
		$this->ical = $feed;
	}
	
	/**
	 * @param string|null $interval
	 *
	 * @return \Generator|Event
	 * @throws \Exception
	 */
	public function GetEvents(?string $interval = NULL) : \Generator
	{
		if ($interval === NULL)
		{
			$events = $this->ical->events();
		}
		else
		{
			$events = $this->ical->eventsFromInterval($interval);
		}
		
		foreach ($events as $event)
		{
			yield new Event($event);
		}
	}
}
