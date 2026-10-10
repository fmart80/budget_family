<?php
declare(strict_types=1);

namespace App\Controllers;

final class GuideController
{
    public function index(): void
    {
        view('guide/index', ['title' => 'Guide & exemples']);
    }
}
