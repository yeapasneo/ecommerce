<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Review;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Reviews', [
            'reviews' => Review::with('user:id,name', 'product:id,name,slug')
                ->when($request->status === 'pending', fn ($q) => $q->where('is_approved', false))
                ->when($request->status === 'approved', fn ($q) => $q->where('is_approved', true))
                ->latest()->paginate(20)->withQueryString(),
            'filters' => $request->only('status'),
        ]);
    }

    public function update(Request $request, Review $review)
    {
        $review->update($request->validate(['is_approved' => ['required', 'boolean']]));

        return back()->with('success', 'Review updated.');
    }

    public function destroy(Review $review)
    {
        $review->delete();

        return back()->with('success', 'Review deleted.');
    }

    public function questions(Request $request)
    {
        return Inertia::render('Admin/Questions', [
            'questions' => Question::with('user:id,name', 'product:id,name,slug')
                ->when($request->status === 'unanswered', fn ($q) => $q->whereNull('answer'))
                ->latest()->paginate(20)->withQueryString(),
            'filters' => $request->only('status'),
        ]);
    }

    public function answer(Request $request, Question $question)
    {
        $data = $request->validate(['answer' => ['required', 'string', 'max:2000']]);
        $question->update($data + ['answered_at' => now()]);

        return back()->with('success', 'Answer saved.');
    }

    public function destroyQuestion(Question $question)
    {
        $question->delete();

        return back()->with('success', 'Question deleted.');
    }
}
