<?php

/**
 * Class Bench
 *
 * Provides a simple way to measure the amount of time , memory
 * that elapses between two points.
 *
 * NOTE: All methods are static since the class is intended
 * to measure throughout an entire application's life cycle.
 *
 * 
 */
class Bench
{

    /**
     * List of all timers.
     *
     * @var array
     */
    private static $timers = [];

    //--------------------------------------------------------------------

    /**
     * Starts a timer running.
     *
     * Multiple calls can be made to this method so that several
     * execution points can be measured.
     *
     * @param string $name The name of this timer.
     * @param float  $time Allows user to provide time.
     *
     * @return Timer
     */
    public static function start($name, $time = null, $memory = null)
    {
        self::$timers[strtolower($name)] = [
            'start' => !empty($time) ? $time : microtime(true),
            'end' => null,
            'start_memory' => !empty($memory) ? $memory : memory_get_usage(),
            'end_memory' => null,
        ];
    }

    //--------------------------------------------------------------------

    /**
     * Stops a running timer.
     *
     * If the timer is not stopped before the timers() method is called,
     * it will be automatically stopped at that point.
     *
     * @param string $name The name of this timer.
     *
     * @return Timer
     */
    public static function stop($name ,$cpu = false)
    {
        $name = strtolower($name);

        if (empty(self::$timers[$name])) {
            throw new \RuntimeException('Cannot stop timer: invalid name given.');
        }

        self::$timers[$name]['end'] = microtime(true);
        self::$timers[$name]['end_memory'] = memory_get_usage();
        self::$timers[$name]['peek'] = memory_get_peak_usage();
if($cpu ){
          $cpuLoad = self::getServerLoad();
          if (is_null($cpuLoad)) {
          $cpuLoad =  "CPU load not estimateable (maybe too old Windows or missing rights at Linux or Windows)";
          }else {
          $cpuLoad=$cpuLoad . "%";
          }
          self::$timers[$name]['cpu_load'] =$cpuLoad;
}else {
	 self::$timers[$name]['cpu_load'] = 'Not enabled';
}
    }

    //--------------------------------------------------------------------

    /**
     * Returns the duration of a recorded timer.
     *
     * @param string  $name     The name of the timer.
     * @param integer $decimals Number of decimal places.
     *
     * @return null|float       Returns null if timer exists by that name.
     *                          Returns a float representing the number of
     *                          seconds elapsed while that timer was running.
     */
    public static function getElapsedTime($name, $decimals = 5)
    {
        $name = strtolower($name);

        if (empty(self::$timers[$name])) {
            return null;
        }

        $timer = self::$timers[$name];

        if (empty($timer['end'])) {
            $timer['end'] = microtime(true);
        }
        if (empty($timer['end_memory'])) {
            $timer['end_memory'] = memory_get_usage();
        }
        $return = array(
            'time' => (float) number_format($timer['end'] - $timer['start'], $decimals),
            'memory' => self::convert($timer['end_memory'] - $timer['start_memory']),
        );
        return $return;
    }

    //--------------------------------------------------------------------

    /**
     * Returns the array of timers, with the duration pre-calculated for you.
     *
     * @param integer $decimals Number of decimal places
     *
     * @return array
     */
    public static function getTimers($extended = null, $decimals = 8)
    {
        $timers = self::$timers;

        foreach ($timers as &$timer) {
            if (empty($timer['end'])) {
                $timer['end'] = microtime(true);
            }
            if (empty($timer['end_memory'])) {
                $timer['end_memory'] = memory_get_usage();
            }

            $timer['duration'] = (float) number_format($timer['end'] - $timer['start'], $decimals);
            $timer['memory'] = self::convert($timer['end_memory'] - $timer['start_memory']);
            $timer['peak'] = self::convert($timer['peek']);
            if (!$extended) {
                unset($timer['start'], $timer['end'], $timer['start_memory'], $timer['end_memory'], $timer['peek']);
            }
        }

        return $timers;
    }

    //--------------------------------------------------------------------

    /**
     * Checks whether or not a timer with the specified name exists.
     *
     * @param string $name
     *
     * @return boolean
     */
    public static function has($name)
    {
        return array_key_exists(strtolower($name), self::$timers);
    }

    private static function _getServerLoadLinuxData()
    {
        if (is_readable("/proc/stat")) {
            $stats = @file_get_contents("/proc/stat");

            if ($stats !== false) {
                // Remove double spaces to make it easier to extract values with explode()
                $stats = preg_replace("/[[:blank:]]+/", " ", $stats);

                // Separate lines
                $stats = str_replace(array("\r\n", "\n\r", "\r"), "\n", $stats);
                $stats = explode("\n", $stats);

                // Separate values and find line for main CPU load
                foreach ($stats as $statLine) {
                    $statLineData = explode(" ", trim($statLine));

                    // Found!
                    if
                    (
                            (count($statLineData) >= 5) &&
                            ($statLineData[0] == "cpu")
                    ) {
                        return array(
                            $statLineData[1],
                            $statLineData[2],
                            $statLineData[3],
                            $statLineData[4],
                        );
                    }
                }
            }
        }

        return null;
    }

    // Returns server load in percent (just number, without percent sign)
    public static function getServerLoad()
    {
        $load = null;

        if (stristr(PHP_OS, "win")) {
            $cmd = "wmic cpu get loadpercentage /all";
            @exec($cmd, $output);

            if ($output) {
                foreach ($output as $line) {
                    if ($line && preg_match("/^[0-9]+\$/", $line)) {
                        $load = $line;
                        break;
                    }
                }
            }
        } else {
            if (is_readable("/proc/stat")) {
                // Collect 2 samples - each with 1 second period
                // See: https://de.wikipedia.org/wiki/Load#Der_Load_Average_auf_Unix-Systemen
                $statData1 = self::_getServerLoadLinuxData();
                sleep(1);
                $statData2 = self::_getServerLoadLinuxData();

                if
                (
                        (!is_null($statData1)) &&
                        (!is_null($statData2))
                ) {
                    // Get difference
                    $statData2[0] -= $statData1[0];
                    $statData2[1] -= $statData1[1];
                    $statData2[2] -= $statData1[2];
                    $statData2[3] -= $statData1[3];

                    // Sum up the 4 values for User, Nice, System and Idle and calculate
                    // the percentage of idle time (which is part of the 4 values!)
                    $cpuTime = $statData2[0] + $statData2[1] + $statData2[2] + $statData2[3];

                    // Invert percentage to get CPU time, not idle time
                    $load = 100 - ($statData2[3] * 100 / $cpuTime);
                }
            }
        }

        return $load;
    }

    public static function printTimers($extended = null)
    {
        echo '<pre>';
        print_r(self::getTimers($extended));
        echo '</pre>';
    }

    //----------------------------
    public static function convert($size)
    {
        $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
    }

    //--------------------------------------------------------------------
}
