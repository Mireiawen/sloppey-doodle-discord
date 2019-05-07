<?php
/** @noinspection SpellCheckingInspection */
declare(strict_types = 1);

namespace Mireiawen\Reader;

/**
 * Event helper class
 *
 * @package Mireiawen\Reader
 */
class Event
{
	/**
	 * Text for the voted Doodle items
	 *
	 * @var string
	 */
	public const STATUS_TEXT_EVENT = 'Doodle';
	
	/**
	 * Text for the Doodle item in voting
	 *
	 * @var string
	 */
	public const STATUS_TEXT_VOTING = 'Doodle-kesken';
	
	/**
	 * Status for items in voting
	 *
	 * @var string
	 */
	public const STATUS_IN_VOTING = 'In voting';
	
	/**
	 * Status for actual raids
	 *
	 * @var string
	 */
	public const STATUS_RAID = 'Raid';
	
	/**
	 * Actual event data
	 *
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
		
		// Change the status text that can be translated into custom non-translated text
		switch ($matches['status'])
		{
		case self::STATUS_TEXT_EVENT:
			$status = self::STATUS_RAID;
			break;
		
		case self::STATUS_TEXT_VOTING:
			$status = self::STATUS_IN_VOTING;
			break;
		
		default:
			throw new \Exception(\sprintf(\_('Invalid status %s'), $matches['status']));
		}
		
		// Cut the description, participants and URL from Doodle message
		// @note: This does require paid Doodle account for it to work and it is translated by the Doodle
		if (\preg_match("/^Aloitteesta\s+.+?\n(?P<description>.*)\sOsallistujat:\s(?P<participants>.*)(?P<url>https:\/\/.+?)$/ms", $event->description, $descriptions) === FALSE)
		{
			throw new \Exception(\sprintf(\_('Unable to parse the Doodle Calendar message')));
		}
		
		if (empty($descriptions))
		{
			throw new \Exception(\sprintf(\_('Invalid message format, possibly free Doodle account')));
		}
		
		$description = \trim($descriptions['description']);
		$url = \trim($descriptions['url']);
		
		// Trim the participants out of the text blob
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
		
		// Generate the start and end dates in UTC
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
			'UID' => $event->uid,
			'DateStart' => $event->dtstart,
		];
	}
	
	/**
	 * Get the event summary text
	 *
	 * @return string
	 */
	public function GetSummary() : string
	{
		return $this->data['Summary'];
	}
	
	/**
	 * Get the event status text
	 *
	 * @return string
	 */
	public function GetStatus() : string
	{
		return $this->data['Status'];
	}
	
	/**
	 * Get the event start time
	 *
	 * @return \DateTime
	 */
	public function GetStart() : \DateTime
	{
		return $this->data['Start'];
	}
	
	/**
	 * Get the event start time
	 *
	 * @return \DateTime
	 */
	public function GetEnd() : \DateTime
	{
		return $this->data['End'];
	}
	
	/**
	 * Get the event duration
	 *
	 * @return \DateInterval
	 */
	public function GetDuration() : \DateInterval
	{
		return $this->data['Duration'];
	}
	
	/**
	 * Get the event description
	 *
	 * @return string
	 */
	public function GetDescription() : string
	{
		return $this->data['Description'];
	}
	
	/**
	 * Get the event attendees
	 *
	 * @return array
	 */
	public function GetAttendees() : array
	{
		return $this->data['Attendees'];
	}
	
	/**
	 * Get the URL for the event
	 *
	 * @return string
	 */
	public function GetURL() : string
	{
		return $this->data['URL'];
	}
	
	/**
	 * Get the event UID
	 *
	 * @return string
	 */
	public function GetUID() : string
	{
		return $this->data['UID'];
	}
	
	/**
	 * Get the event date start
	 *
	 * @return string
	 */
	public function GetDateStart() : string
	{
		return $this->data['DateStart'];
	}
	
	/**
	 * Combine 2 events into one
	 *
	 * @param Event $event
	 */
	public function Merge(Event $event) : void
	{
		$this->data['End'] = $event->GetEnd();
		$this->data['Duration'] = $this->GetEnd()->diff($this->GetStart());
	}
}
