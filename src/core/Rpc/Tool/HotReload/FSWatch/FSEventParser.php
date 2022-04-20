<?php

namespace SPF\Rpc\Tool\HotReload\FSWatch;

/**
 * Class FSEventParser
 */
class FSEventParser
{
    protected const REGEX = '/^(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\s+(\S+)\s+(.*)/m';

    /**
     * @param string $event
     * @param array $eventTypes
     *
     * @return []
     */
    public static function toEvent(string $event, array $eventTypes): ?array
    {
        $logs = [];
        if (preg_match_all(static::REGEX, $event, $matches)) {
            foreach ($matches[3] as $idx => $match) {
                $events = explode(' ', $match);
                $events = array_intersect($eventTypes, $events);
                if (count($events) === 0) {
                    continue;
                }
                $date = $matches[1][$idx];
                $path = $matches[2][$idx];
                $resourceType = is_dir($path) ? 'Directory' : 'File';

                $logs[] = sprintf('%s: %s %s at %s', $resourceType, $path, implode(', ', $events), $date);
            }
        }

        return $logs;
    }
}
