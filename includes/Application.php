<?php
declare(strict_types = 1);

namespace Reader;

use AG\DiscordMsg;
use ICal\ICal;
use Reader\Config;
use Reader\Reader;
use Reader\Event;
use Smarty;

class Application
{
	protected $config;
	
	public function __construct(Config $cfg)
	{
		$this -> config = $cfg;
	}

	public static function Run() : void
	{
		$config = new Config('config.json');
		$app = new Application($config);
		
		$ical = new ICal();
		$ical->initUrl($config -> GetCalendarFeed());
		$reader = new Reader($ical);
		$events = $app->ReadCombinedEvents($reader);
		
		$templates = $app->GenerateTemplates($events);
		$app->SendMessages($templates);
	}
	
	public function ReadCombinedEvents(Reader $reader) : \Generator
	{
		$prev = NULL;
		
		foreach ($reader->GetEvents($this -> config -> GetCalendarInterval()) as $event)
		{
			if ($event->GetStatus() === Event::STATUS_IN_VOTING)
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
	
	public function GenerateTemplates(\Generator $events): \Generator
	{
		$smarty = new Smarty();
		$tz = new \DateTimeZone('UTC');
		$now = new \DateTime('now', $tz);
		
		foreach ($events as $event)
		{
			$start = $event->GetStart()->diff($now);
			if ($start->days)
			{
				if ($start->days > 1)
				{
					$d = new \DateTime($start->format('+%a days'), $tz);
					$start = sprintf('on %s', $d->format('l'));
				}
				else
				{
					$start = 'tomorrow';
				}
			}
			else
			{
				$start = $start->format('in %h hours and %i minutes');
			}
			$duration = $this->GetDurationMessage($event->GetDuration());
			
			$tpl = $smarty->createTemplate('templates/event.tpl.txt');
			$tpl->assign('title', $event->GetSummary());
			$tpl->assign('time', $start);
			$tpl->assign('duration', $duration);
			$tpl->assign('message', $event->GetDescription());
			$tpl->assign('participants', $event->GetAttendees());
			$tpl->assign('url', $event->GetURL());
			
			yield $tpl;
		}
	}
	
	public function SendMessages(\Generator $templates): void
	{
		foreach ($templates as $tpl)
		{
			echo $tpl->fetch(), "\n";
			$msg = new DiscordMsg(
				$tpl->fetch(), 
				$this->config->GetDiscordHook(), 
				$this->config->GetDiscordUser(), 
				$this->config->GetDiscordAvatar()
			);
			
			#$msg->send();
		}
	}
	
	protected function GetDurationMessage(\DateInterval $duration): string
	{
		if ($duration->m < 10)
		{
			return $duration->format('%h hours');
		}
		
		if ($duration->m < 20)
		{
			return $duration->format( '%h hours 15 minutes');
		}
		
		if ($duration->m < 40)
		{
			return $duration->format('%h hours 30 minutes');
		}
		
		if ($duration->m < 50)
		{
			return $duration->format('%h hours 45 minutes');
		}

		return sprintf('%d hours', $event->GetDuration()->h + 1);
	}
}
