<?php

class Calc
{
    public function merge(array $a, array $b): array
    {
        if ($b[0] > $a[1] + 1) {
            $tmp = [$a, $b];
        } elseif ($b[1] <= $a[1]) {
            $tmp = [$a];
        } else {
            $tmp = [[$a[0], $b[1]]];
        }
        return array_filter($tmp);
    }

    public function substract(array $a, array $b): array
    {
        if ($b[0] > $a[1] || $b[1] < $a[0]) {
            return [$a];
        } elseif ($b[0] <= $a[0] && $b[1] >= $a[1]) {
            return [];
        } elseif ($b[0] > $a[0] && $b[1] < $a[1]) {
            return [[$a[0], $b[0] - 1], [$b[1] + 1, $a[1]]];
        } elseif ($b[0] <= $a[0]) {
            return [$b[1] + 1, $a[1]];
        } else {
            return [$a[0], $b[1] - 1];
        }
    }

    public function alignment(array $arr): array
    {
        usort($arr, fn ($a, $b) => $a[0] <=> $b[0]);
        $tmp = [[]];
        foreach ($arr as $k => $v) {
            $t   = array_pop($tmp);
            $tmp = array_merge($tmp, $this->merge($t, $v));
        }
        return $tmp;
    }

    public function prepare(array $include, array $exclude): array
    {
        $include = $this->alignment($include);
        if (!empty($exclude)) {
            $tmp = [];
            $exclude = $this->alignment($exclude);
            foreach ($exclude as $k => $v) {
                foreach ($include as $i => $j) {
                    $tmp = array_merge($tmp, $this->substract($j, $v));
                }
                $d = array_filter($tmp);
            }
            return $tmp;
        }
        return $include;
    }

    public function toCIDR(int $a, int $b)
    {
        $diff = $b - $a + 1;
        if ($diff > 1) {
            for ($i = 0; $i <= 32; $i++) {
                if ($diff / (1 << $i) < 1) {
                    break;
                }
            }
            $tmp[] = long2ip($a) . "/" . (32 - $i + 1);
            $new = $a + (1 << ($i - 1));
            if ($new <= $b) {
                $tmp = array_merge($tmp, $this->toCIDR($new, $b));
            }
        } else {
            $tmp[] = long2ip($a) . "/32";
        }
        return $tmp;
    }
}
