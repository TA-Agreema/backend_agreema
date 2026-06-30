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
        /** @var \App\Models\User $user */
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

        $lastMonthContracts = Contract::whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->count();

        $thisMonthContracts = Contract::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        $growth = 0;
        if ($lastMonthContracts > 0) {
            $growth = round((($thisMonthContracts - $lastMonthContracts) / $lastMonthContracts) * 100);
        } else if ($thisMonthContracts > 0) {
            $growth = 100;
        }

        $overdueContracts = Contract::where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', Carbon::today())
            ->count();

        $distribution = Contract::select('status', DB::raw('count(*) as total'))
            ->where('status', '!=', 'signed')
            ->groupBy('status')
            ->get();

        $recentLogs = ContractStatusLog::with(['contract:id,title,contract_number', 'changedBy:id,name'])
            ->latest()
            ->take(15)
            ->get();

        $expiringContracts = Contract::where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', Carbon::now()->addDays(60))
            ->orderBy('end_date', 'asc')
            ->get(['id', 'title', 'contract_number', 'partner_name', 'end_date']);

        $approvalTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);

            $approvedCount = ContractStatusLog::where('new_status', 'approved')
                ->whereMonth('created_at', $monthDate->month)
                ->whereYear('created_at', $monthDate->year)
                ->count();

            $rejectedCount = ContractStatusLog::whereIn('new_status', ['rejected', 'terminated'])
                ->whereMonth('created_at', $monthDate->month)
                ->whereYear('created_at', $monthDate->year)
                ->count();

            $approvalTrend[] = [
                'month' => $monthDate->translatedFormat('M'),
                'approved' => $approvedCount,
                'rejected' => $rejectedCount
            ];
        }

        return [
            'role' => 'admin',
            'metrics' => [
                'total_contracts' => $totalContracts,
                'active_contracts' => $activeContracts,
                'growth' => $growth,
                'overdue_contracts' => $overdueContracts,
            ],
            'distribution' => $distribution,
            'recent_logs' => $recentLogs,
            'expiring_contracts' => $expiringContracts,
            'approval_trend' => $approvalTrend,
        ];
    }

    private function hrdDashboard($user): array
    {
        $totalMyContracts = Contract::where('created_by', $user->id)->count();
        $activeMyContracts = Contract::where('status', 'active')->where('created_by', $user->id)->count();
        $rejected = Contract::where('status', 'rejected')->where('created_by', $user->id)->count();
        $actionNeeded = Contract::whereIn('status', ['draft', 'revision'])->where('created_by', $user->id)->count();
        $waitingReview = Contract::where('status', 'review')->where('created_by', $user->id)->count();
        $approved = Contract::where('status', 'approved')->where('created_by', $user->id)->count();

        $distribution = Contract::where('created_by', $user->id)
            ->where('status', '!=', 'signed')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        $recentLogs = ContractStatusLog::with(['contract:id,title,contract_number', 'changedBy:id,name'])
            ->whereHas('contract', function ($q) use ($user) {
                $q->where('created_by', $user->id);
            })
            ->latest()
            ->take(15)
            ->get();

        $expiringContracts = Contract::where('status', 'active')
            ->where('created_by', $user->id)
            ->whereNotNull('end_date')
            ->where('end_date', '<=', Carbon::now()->addDays(60))
            ->orderBy('end_date', 'asc')
            ->get(['id', 'title', 'contract_number', 'partner_name', 'end_date']);

        $approvalTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);

            $approvedCount = ContractStatusLog::whereHas('contract', function ($q) use ($user) {
                $q->where('created_by', $user->id);
            })
                ->where('new_status', 'approved')
                ->whereMonth('created_at', $monthDate->month)
                ->whereYear('created_at', $monthDate->year)
                ->count();

            $rejectedCount = ContractStatusLog::whereHas('contract', function ($q) use ($user) {
                $q->where('created_by', $user->id);
            })
                ->whereIn('new_status', ['rejected', 'terminated'])
                ->whereMonth('created_at', $monthDate->month)
                ->whereYear('created_at', $monthDate->year)
                ->count();

            $approvalTrend[] = [
                'month' => $monthDate->translatedFormat('M'),
                'approved' => $approvedCount,
                'rejected' => $rejectedCount
            ];
        }

        return [
            'role' => 'hrd',
            'metrics' => [
                'my_contracts' => $totalMyContracts,
                'active_my_contracts' => $activeMyContracts,
                'rejected' => $rejected,
                'action_needed' => $actionNeeded,
                'waiting_review' => $waitingReview,
                'approved' => $approved,
            ],
            'distribution' => $distribution,
            'recent_logs' => $recentLogs,
            'expiring_contracts' => $expiringContracts,
            'approval_trend' => $approvalTrend,
        ];
    }

    private function managerDashboard($user): array
    {
        $managedContracts = Contract::whereHas('signers', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });

        $waitingApproval = (clone $managedContracts)
            ->where('status', 'review')
            ->count();

        $waitingSignature = ContractSigner::where('user_id', $user->id)
            ->whereHas('contract', function ($q) {
                $q->where('status', 'approved');
            })->count();

        $totalApprovedByMe = (clone $managedContracts)
            ->whereHas('statusLogs', function ($q) use ($user) {
                $q->where('changed_by', $user->id)
                    ->where('new_status', 'approved');
            })
            ->count();

        $totalRejectedByMe = (clone $managedContracts)
            ->whereHas('statusLogs', function ($q) use ($user) {
                $q->where('changed_by', $user->id)
                    ->whereIn('new_status', ['rejected', 'terminated']);
            })
            ->count();

        $topWaiting = (clone $managedContracts)
            ->where('status', 'review')
            ->with(['creator:id,name'])
            ->latest()
            ->take(5)
            ->get(['id', 'title', 'contract_number', 'created_by', 'created_at']);

        $approvalTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);

            $approvedCount = ContractStatusLog::where('changed_by', $user->id)
                ->where('new_status', 'approved')
                ->whereMonth('created_at', $monthDate->month)
                ->whereYear('created_at', $monthDate->year)
                ->count();

            $rejectedCount = ContractStatusLog::where('changed_by', $user->id)
                ->where('new_status', 'rejected')
                ->whereMonth('created_at', $monthDate->month)
                ->whereYear('created_at', $monthDate->year)
                ->count();

            $approvalTrend[] = [
                'month' => $monthDate->translatedFormat('M'),
                'approved' => $approvedCount,
                'rejected' => $rejectedCount
            ];
        }

        return [
            'role' => 'manager',
            'metrics' => [
                'waiting_approval' => $waitingApproval,
                'waiting_signature' => $waitingSignature,
                'total_approved' => $totalApprovedByMe,
                'total_rejected' => $totalRejectedByMe,
            ],
            'top_waiting' => $topWaiting,
            'approval_trend' => $approvalTrend,
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
