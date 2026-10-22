<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Score;

final class ScoreController
{
    public function index(): void
    {
        $result = Score::compute();
        $history = [];
        foreach (range(5, 0) as $i) {
            $m = date('Y-m', strtotime("-$i months", strtotime(date('Y-m-01'))));
            $history[] = ['month' => $m, 'rate' => Score::monthRate($m)];
        }
        view('score/index', [
            'title'   => 'Score financier',
            'result'  => $result,
            'history' => Score::recordAndHistory($result['score'], $result['criteria']),
            'rates'   => $history,
        ]);
    }
}
