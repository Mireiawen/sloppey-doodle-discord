<?php
declare(strict_types = 1);

namespace Mireiawen\Reader;

use AG\DiscordMsg;
use ICal\ICal;
use Smarty;

/**
 * Main application class
 *
 * @package Mireiawen\Reader
 */
class Application
{
	/**
	 * Initial spam sent
	 *
	 * @var string
	 */
	protected const SENT_INITIAL = 'INITIAL MESSAGE SENT';
	
	/**
	 * Spam for tomorrow sent
	 *
	 * @var string
	 */
	protected const SENT_TOMORROW = 'TOMORROW MESSAGE SENT';
	
	/**
	 * Spam for 2 hours sent
	 *
	 * @var string
	 */
	protected const SENT_TWOHOURS = 'TWO HOURS MESSAGE SENT';
	
	/**
	 * Two hours in seconds
	 *
	 * @var int
	 */
	protected const TIME_TWOHOURS = 2 * 60 * 60;
	
	/**
	 * Instance of the cache backend
	 *
	 * @var Redis
	 */
	protected $cache;
	
	/**
	 * Instance of the configuration instance
	 *
	 * @var Config
	 */
	protected $config;
	
	/**
	 * Application constructor.
	 *
	 * @param Config $cfg
	 * @param Redis $cache
	 */
	public function __construct(Config $cfg, Redis $cache)
	{
		$this->config = $cfg;
		$this->cache = $cache;
	}
	
	/**
	 * Run the actual application
	 *
	 * @throws \Exception
	 */
	public static function Run() : void
	{
		$config = new Config('config.json');
		$cache = Redis::CreateConnection($config->Get('CacheHostname', 'localhost'));
		$app = new Application($config, $cache);
		
		if ($config->Get('Debug', FALSE))
		{
			$cache->Flush('calendar_events');
		}
		
		if ($cache->Exists('calendar_events'))
		{
			$events = unserialize($cache->Fetch('calendar_events'));
		}
		else
		{
			$ical = new ICal();
			$ical->initUrl($config->Get('CalendarFeed'));
			$reader = new Reader($ical);
			$events = iterator_to_array($app->ReadCombinedEvents($reader));
			$cache->Store('calendar_events', serialize($events), $config->Get('CacheTimeout', 3600));
		}
		
		$templates = $app->GenerateTemplates($events);
		$app->SendMessages($templates);
	}
	
	/**
	 * Combine multiple events next to each other into one large event
	 *
	 * @param Reader $reader
	 *
	 * @return \Generator|Event
	 * @throws \Exception
	 */
	public function ReadCombinedEvents(Reader $reader) : \Generator
	{
		/** @var Event|NULL $prev */
		$prev = NULL;
		
		/** @var Event $event */
		$event = NULL;
		
		$now = new \DateTime('now');
		
		foreach ($reader->GetEvents($this->config->Get('CalendarInterval')) as $event)
		{
			if ($event->GetStatus() === Event::STATUS_IN_VOTING)
			{
				continue;
			}
			
			if ($event->GetStart() <= $now)
			{
				continue;
			}
			
			if ($prev !== NULL)
			{
				if ($prev->GetEnd() == $event->GetStart())
				{
					$prev->Merge($event);
					continue;
				}
			}
			
			if ($prev !== NULL)
			{
				yield $prev;
			}
			$prev = $event;
		}
		if ($prev !== NULL)
		{
			yield $prev;
		}
	}
	
	/**
	 * @param array|Event $events
	 *
	 * @return \Generator
	 *
	 * @throws \SmartyException
	 * @throws \Exception
	 */
	public function GenerateTemplates(array $events) : \Generator
	{
		$smarty = new Smarty();
		
		foreach ($events as $event)
		{
			$start = $this->GetStartDay($event);
			$duration = $this->GetDurationMessage($event->GetDuration());
			
			$tpl = $smarty->createTemplate('templates/event.tpl.txt');
			$tpl->assign('id', \sprintf('%s/%s', $event->GetUID(), $event->GetDateStart()));
			$tpl->assign('timestamp_start', $event->GetStart()->getTimestamp());
			$tpl->assign('title', $event->GetSummary());
			$tpl->assign('time', $start);
			$tpl->assign('duration', $duration);
			$tpl->assign('message', $event->GetDescription());
			$tpl->assign('participants', $event->GetAttendees());
			$tpl->assign('url', $event->GetURL());
			
			yield $tpl;
		}
	}
	
	/**
	 * @param \Generator|\Smarty_Template_Source $templates
	 *
	 * @throws \Exception
	 */
	public function SendMessages(\Generator $templates) : void
	{
		foreach ($templates as $tpl)
		{
			$msgid = $tpl->getTemplateVars('id');
			$timestamp = $tpl->getTemplateVars('timestamp_start');
			if (!$this->ShouldSendMessage($msgid, $timestamp))
			{
				continue;
			}
			
			echo $tpl->fetch(), "\n";
			$msg = new DiscordMsg(
				$tpl->fetch(),
				$this->config->Get('DiscordHook'),
				$this->config->Get('DiscordUser'),
				$this->config->Get('DiscordAvatar')
			);
			if (!$this->config->Get('Debug', FALSE))
			{
				$msg->send();
			}
		}
	}
	
	/**
	 * @param string $msgid
	 * @param int $timestamp
	 *
	 * @return bool
	 *
	 * @throws \Exception
	 */
	protected function ShouldSendMessage(string $msgid, int $timestamp) : bool
	{
		// Initial message
		if (!$this->cache->Exists($msgid))
		{
			$this->cache->Store($msgid, self::SENT_INITIAL, Redis::PERSISTENT);
			return TRUE;
		}
		
		$sent_status = $this->cache->Fetch($msgid);
		
		// Check for tomorrow
		if ($sent_status === self::SENT_INITIAL)
		{
			$event_start = \DateTime::createFromFormat('U', (string)$timestamp);
			$tz = new \DateTimeZone('UTC');
			$now = new \DateTime('now', $tz);
			$tomorrow = new \DateTime($now->format('Y-m-d'), $tz);
			$tomorrow->modify('+1 day');
			
			if ($tomorrow->format('Y-m-d') === $event_start->format('Y-m-d'))
			{
				$this->cache->Store($msgid, self::SENT_TOMORROW, Redis::PERSISTENT);
				return TRUE;
			}
			
			return FALSE;
		}
		
		// Check for 2h timestamp
		if ($sent_status === self::SENT_TOMORROW)
		{
			if (\time() - $timestamp < self::TIME_TWOHOURS)
			{
				$this->cache->Store($msgid, self::SENT_TWOHOURS, Redis::PERSISTENT);
				return TRUE;
			}
			
			return FALSE;
		}
		
		return FALSE;
	}
	
	/**
	 * @param Event $event
	 *
	 * @return string
	 * @throws \Exception
	 */
	protected function GetStartDay(Event $event) : string
	{
		$tz = new \DateTimeZone('UTC');
		$now = new \DateTime('now', $tz);
		$start = $event->GetStart()->diff($now);
		$tomorrow = new \DateTime($now->format('Y-m-d'), $tz);
		$tomorrow->modify('+1 day');
		
		// Check for today
		if ($now->format('Y-m-d') === $event->GetStart()->format('Y-m-d'))
		{
			return $start->format('in %h hours and %i minutes');
		}
		
		// Check for tomorrow
		if ($tomorrow->format('Y-m-d') === $event->GetStart()->format('Y-m-d'))
		{
			return \_('tomorrow');
		}
		
		// Other days
		return \sprintf(\_('on %s'), $event->GetStart()->format('l'));
	}
	
	/**
	 * @param \DateInterval $duration
	 *
	 * @return string
	 */
	protected function GetDurationMessage(\DateInterval $duration) : string
	{
		if ($duration->m < 10)
		{
			return $duration->format('%h hours');
		}
		
		if ($duration->m < 20)
		{
			return $duration->format('%h hours 15 minutes');
		}
		
		if ($duration->m < 40)
		{
			return $duration->format('%h hours 30 minutes');
		}
		
		if ($duration->m < 50)
		{
			return $duration->format('%h hours 45 minutes');
		}
		
		return sprintf('%d hours', $duration->h + 1);
	}
}
