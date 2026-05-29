<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Contract;
use App\Models\ContractStatusLog;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\ContractSigner;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            return response()->json($this->adminDashboard());
        }

        if ($user->hasRole('manager')) {
            return response()->json($this->managerDashboard($user));
        }

        if ($user->hasRole('hrd')) {
            return response()->json($this->hrdDashboard($user));
        }

        // Default internal user dashboard
        return response()->json($this->internalDashboard($user));
    }

    private function adminDashboard(): array
    {
        $totalContracts = Contract::count();
        $activeContracts = Contract::where('status', 'active')->count();

        $distribution = Contract::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        $recentLogs = ContractStatusLog::with(['contract:id,title,contract_number', 'changedBy:id,name'])
            ->latest()
            ->take(15)
            ->get();

        $expiringContracts = Contract::with(['parties.party.companyDetail', 'parties.party.individualDetail'])
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', Carbon::now()->addDays(60))
            ->orderBy('end_date', 'asc')
            ->get(['id', 'title', 'contract_number', 'end_date']);

        return [
            'role' => 'admin',
            'metrics' => [
                'total_contracts' => $totalContracts,
                'active_contracts' => $activeContracts,
            ],
            'distribution' => $distribution,
            'recent_logs' => $recentLogs,
            'expiring_contracts' => $expiringContracts,
        ];
    }

    private function hrdDashboard($user): array
    {
        $totalSystemActive = Contract::where('status', 'active')->count();
        $totalMyContracts = Contract::where('created_by', $user->id)->count();
        $actionNeeded = Contract::whereIn('status', ['draft', 'revision'])->where('created_by', $user->id)->count();
        $waitingReview = Contract::where('status', 'review')->where('created_by', $user->id)->count();
        $approved = Contract::where('status', 'approved')->where('created_by', $user->id)->count();

        $distribution = Contract::where('created_by', $user->id)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        $systemDistribution = Contract::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        $recentLogs = ContractStatusLog::with(['contract:id,title,contract_number', 'changedBy:id,name'])
            ->whereHas('contract', function ($q) use ($user) {
                $q->where('created_by', $user->id);
            })
            ->latest()
            ->take(15)
            ->get();

        $expiringContracts = Contract::with(['parties.party.companyDetail', 'parties.party.individualDetail'])
            ->where('status', 'active')
            ->where('created_by', $user->id)
            ->whereNotNull('end_date')
            ->where('end_date', '<=', Carbon::now()->addDays(60))
            ->orderBy('end_date', 'asc')
            ->get(['id', 'title', 'contract_number', 'end_date']);

        return [
            'role' => 'hrd',
            'metrics' => [
                'total_system_active' => $totalSystemActive,
                'my_contracts' => $totalMyContracts,
                'action_needed' => $actionNeeded,
                'waiting_review' => $waitingReview,
                'approved' => $approved,
            ],
            'distribution' => $distribution,
            'system_distribution' => $systemDistribution,
            'recent_logs' => $recentLogs,
            'expiring_contracts' => $expiringContracts,
        ];
    }

    private function managerDashboard($user): array
    {
        $totalSystemActive = Contract::where('status', 'active')->count();
        $waitingApproval = Contract::where('status', 'review')
            ->whereHas('signers', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->count();

        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $approvedThisMonth = ContractStatusLog::where('changed_by', $user->id)
            ->where('new_status', 'approved')
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->distinct('contract_id')
            ->count('contract_id');

        $rejectedOrRevision = ContractStatusLog::where('changed_by', $user->id)
            ->whereIn('new_status', ['revision', 'rejected'])
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->distinct('contract_id')
            ->count('contract_id');

        $topWaiting = Contract::where('status', 'review')
            ->with(['creator:id,name'])
            ->whereHas('signers', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->latest()
            ->take(5)
            ->get(['id', 'title', 'contract_number', 'created_by', 'created_at']);

        return [
            'role' => 'manager',
            'metrics' => [
                'total_system_active' => $totalSystemActive,
                'waiting_approval' => $waitingApproval,
                'approved_this_month' => $approvedThisMonth,
                'revision_requested_this_month' => $rejectedOrRevision,
            ],
            'top_waiting' => $topWaiting,
        ];
    }

    private function internalDashboard($user): array
    {
        // Simple internal dashboard (example)
        $waitingSignature = ContractSigner::where('user_id', $user->id)
            ->whereHas('contract', function ($q) {
                $q->where('status', 'approved');
            })->count();

        return [
            'role' => 'internal',
            'metrics' => [
                'waiting_signature' => $waitingSignature,
            ]
        ];
    }
}
