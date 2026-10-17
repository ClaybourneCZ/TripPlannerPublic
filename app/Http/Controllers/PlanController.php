<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanController extends Controller
{
    //
    public function index(Request $request){
        return Inertia::render('plans/Plan', [
            'tripId' => $request->query('trip'),
        ]);
    }
}
