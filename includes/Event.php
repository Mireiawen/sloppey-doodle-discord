<?php
/** @noinspection SpellCheckingInspection */
declare(strict_types = 1);

namespace Reader;

class Event
{
	/**
	 * @var string
	 */
	public const STATUS_IN_VOTING = 'In voting';
	
	/**
	 * @var string
	 */
	public const STATUS_RAID = 'Raid';
	
	/**
	 * @var array
	 */
	protected $data;
	
	/**
	 * Event constructor.
	 *
	 * @param \ICal\Event $event
	 *
	 * @throws \Exception
	 */
	public function __construct(\ICal\Event $event)
	{
		// Cut the actual text and the Doodle status from Doodle summary
		if (\preg_match('/^(?P<summary>.*)\s*\[(?P<status>.*?)\]$/', $event->summary, $matches) === FALSE)
		{
			throw new \Exception(\sprintf(\_('Unable to parse the Doodle Calendar subject line "%s"'), $event->summary));
		}
		
		if (empty($matches))
		{
			throw new \Exception(\sprintf(\_('Got invalid data from the Doodle Calendar')));
		}
		
		switch ($matches['status'])
		{
		case 'Doodle':
			$status = self::STATUS_RAID;
			break;
		
		case 'Doodle-kesken':
			$status = self::STATUS_IN_VOTING;
			break;
		
		default:
			throw new \Exception(\sprintf(\_('Invalid status %s'), $matches['status']));
		}
		
		// Cut the description, participants and URL from Doodle message
		if (\preg_match("/^Aloitteesta\s+.+?\n(?P<description>.*)\sOsallistujat:\s(?P<participants>.*)(?P<url>https:\/\/.+?)$/ms", $event->description, $descriptions) === FALSE)
		{
			throw new \Exception(\sprintf(\_('Unable to parse the Doodle Calendar message')));
		}
		
		$description = \trim($descriptions['description']);
		$url = \trim($descriptions['url']);
		
		$participants = [];
		foreach (\explode("\n", $descriptions['participants']) as $participant)
		{
			$pos = \strpos($participant, '-');
			if ($pos !== FALSE)
			{
				$participant = \trim(\substr($participant, $pos + 1));
			}
			else
			{
				$participant = \trim($participant);
			}
			
			if (empty($participant))
			{
				continue;
			}
			
			$participants[] = $participant;
		}
		sort($participants);
		
		$tz = new \DateTimeZone('UTC');
		$start = new \DateTime($event->dtstart, $tz);
		$end = new \DateTime($event->dtend, $tz);
		
		// Save the fetched data
		$this->data = [
			'Summary' => \trim($matches['summary']),
			'Status' => $status,
			'Start' => $start,
			'End' => $end,
			'Duration' => $end->diff($start),
			'Description' => $description,
			'Attendees' => $participants,
			'URL' => $url,
		];
	}
	
	/**
	 * Magic method to have GetX methods to work with less work than writing each one manually
	 *
	 * @param string $name
	 * @param array $params
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function __call(string $name, array $params)
	{
		if (\preg_match('/^Get(?P<name>.*)$/', $name, $matches) === FALSE)
		{
			throw new \Exception(\sprintf(\_('Invalid call to %s'), $name));
		}
		
		if (!isset($matches['name']))
		{
			throw new \Exception(\sprintf(\_('Invalid call to %s'), $name));
		}
		
		if (!empty($params))
		{
			throw new \Exception(\sprintf(\_('Invalid amount of parameters for %s'), $name));
		}
		
		return $this->__get($matches['name']);
	}
	
	/**
	 * Magic method to get values from data array
	 *
	 * @param string $key
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function __get(string $key)
	{
		if (!$this->__isset($key))
		{
			throw new \Exception(\sprintf(\_('The key %s is not available'), $key));
		}
		
		return $this->data[$key];
	}
	
	/**
	 * Magic method to check if value exists
	 *
	 * @param $name
	 *
	 * @return bool
	 */
	public function __isset(string $name) : bool
	{
		return \array_key_exists($name, $this->data);
	}
	
	/**
	 * @param Event $event
	 */
	public function Merge(Event $event): void
	{
		$this->data['End'] = $event->GetEnd();
		$this->data['Duration'] = $this->GetEnd()->diff($this->GetStart());
	}
	
}
