<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** Rows per page for admin lists, from ?per_page= (10, 20, 50 or 100). */
    protected function perPage(): int
    {
        $n = (int) request('per_page');

        return in_array($n, [10, 20, 50, 100], true) ? $n : 20;
    }
}
