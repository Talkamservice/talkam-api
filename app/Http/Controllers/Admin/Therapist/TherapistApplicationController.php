<?php

namespace App\Http\Controllers\Admin\Therapist;

use App\Constants\General\NotificationConstants;
use App\Http\Controllers\Controller;
use App\Models\TherapistApplication;
use App\Services\Therapist\TherapistReviewService;
use Illuminate\Http\Request;
use Exception;

/**
 * Web interface for therapist application management (Blade views)
 */
class TherapistApplicationController extends Controller
{
    protected $reviewService;

    public function __construct()
    {
        $this->reviewService = new TherapistReviewService();
    }

    public function index(Request $request)
    {
        $query = TherapistApplication::with('user');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Date range filter
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $applications = $query->latest()->paginate(15);

        // Statistics
        $statistics = [
            'total' => TherapistApplication::count(),
            'draft' => TherapistApplication::where('status', 'draft')->count(),
            'submitted' => TherapistApplication::whereIn('status', ['submitted', 'in_review'])->count(),
            'approved' => TherapistApplication::where('status', 'approved')->count(),
            'rejected' => TherapistApplication::where('status', 'rejected')->count(),
        ];

        return view('dashboards.admin.pages.therapist.applications', compact('applications', 'statistics'));
    }

    public function show($id)
    {
        $application = TherapistApplication::with(['user', 'documents', 'specialties.category'])
            ->findOrFail($id);

        return view('dashboards.admin.pages.therapist.show', compact('application'));
    }

    public function approve($id)
    {
        try {
            $application = $this->reviewService->approve($id);
            
            return back()->with(
                NotificationConstants::SUCCESS_MSG,
                'Application approved successfully! Therapist account has been created.'
            );
        } catch (Exception $e) {
            return back()->with(
                NotificationConstants::ERROR_MSG,
                'Error: ' . $e->getMessage()
            );
        }
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:2000'
        ]);

        try {
            $application = $this->reviewService->reject($id, $request->only('reason'));
            
            return back()->with(
                NotificationConstants::SUCCESS_MSG,
                'Application rejected successfully.'
            );
        } catch (Exception $e) {
            return back()->with(
                NotificationConstants::ERROR_MSG,
                'Error: ' . $e->getMessage()
            );
        }
    }

    public function documentVerdict(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'reason' => 'nullable|string|max:2000|required_if:status,rejected'
        ]);

        try {
            $document = $this->reviewService->documentVerdict($id, $request->only('status', 'reason'));
            
            return back()->with(
                NotificationConstants::SUCCESS_MSG,
                'Document ' . $request->status . ' successfully.'
            );
        } catch (Exception $e) {
            return back()->with(
                NotificationConstants::ERROR_MSG,
                'Error: ' . $e->getMessage()
            );
        }
    }
}
