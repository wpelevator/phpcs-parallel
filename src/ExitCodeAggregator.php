<?php

namespace WPElevator\RunParallel;

final class ExitCodeAggregator
{
    public function aggregate(int $current, int $next): int
    {
        if ($next >= 3 || $current >= 3) {
            return max($current, $next, 3);
        }

        return max($current, $next);
    }
}
