'use client'

import { useQuery } from '@tanstack/react-query'
import { apiClient } from '@/lib/api'
import { formatCurrency } from '@/lib/utils'
import { StatsCard } from '@/components/StatsCard'
import { PaymentsTable } from '@/components/PaymentsTable'
import { MethodsChart } from '@/components/Charts'
import { HiOutlineCheck, HiOutlineClock, HiOutlineCash, HiOutlineCalendar } from 'react-icons/hi'
import Link from 'next/link'

export default function DashboardPage() {
  const { data, isLoading, error } = useQuery({
    queryKey: ['dashboard'],
    queryFn: apiClient.getDashboardOverview,
  })

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="card text-center py-12">
        <div className="text-red-500 mb-4">
          <svg className="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h3 className="text-lg font-semibold text-gray-900 mb-2">Failed to load dashboard data</h3>
        <p className="text-gray-500 mb-4">
          {(error as Error).message || 'Unable to connect to the API'}
        </p>
        <div className="space-x-3">
          <button onClick={() => window.location.reload()} className="btn-primary">
            Retry
          </button>
          <button 
            onClick={() => {
              localStorage.removeItem('payhobe_api_url')
              localStorage.removeItem('payhobe_api_token')
              window.location.href = '/login'
            }} 
            className="btn-secondary"
          >
            Reconnect
          </button>
        </div>
      </div>
    )
  }

  const { stats, recent_payments, pending_payments } = data!

  // Ensure stats have default values
  const safeStats = {
    confirmed_count: stats?.confirmed_count ?? 0,
    pending_count: stats?.pending_count ?? 0,
    total_confirmed: stats?.total_confirmed ?? 0,
    today_amount: stats?.today_amount ?? 0,
    by_method: stats?.by_method ?? {},
  }

  // Ensure arrays have default values
  const safePendingPayments = pending_payments ?? []
  const safeRecentPayments = recent_payments ?? []

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p className="text-gray-500 mt-1">Welcome to your PayHobe dashboard</p>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatsCard
          title="Confirmed Payments"
          value={safeStats.confirmed_count.toLocaleString()}
          icon={<HiOutlineCheck className="h-6 w-6" />}
          color="green"
        />
        <StatsCard
          title="Pending Payments"
          value={safeStats.pending_count.toLocaleString()}
          icon={<HiOutlineClock className="h-6 w-6" />}
          color="yellow"
        />
        <StatsCard
          title="Total Confirmed"
          value={formatCurrency(safeStats.total_confirmed)}
          icon={<HiOutlineCash className="h-6 w-6" />}
          color="blue"
        />
        <StatsCard
          title="Today's Revenue"
          value={formatCurrency(safeStats.today_amount)}
          icon={<HiOutlineCalendar className="h-6 w-6" />}
          color="purple"
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Payment Methods Chart */}
        <div className="card">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Payment Methods</h2>
          {safeStats.by_method && Object.keys(safeStats.by_method).length > 0 ? (
            <MethodsChart data={safeStats.by_method} />
          ) : (
            <p className="text-gray-500 text-center py-8">No data yet</p>
          )}
        </div>

        {/* Pending Payments */}
        <div className="lg:col-span-2 card">
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-lg font-semibold text-gray-900">Pending Verification</h2>
            {safePendingPayments.length > 0 && (
              <Link href="/dashboard/payments?status=pending" className="text-primary-600 text-sm hover:underline">
                View all →
              </Link>
            )}
          </div>
          {safePendingPayments.length > 0 ? (
            <PaymentsTable payments={safePendingPayments.slice(0, 5)} />
          ) : (
            <p className="text-gray-500 text-center py-8">No pending payments 🎉</p>
          )}
        </div>
      </div>

      {/* Recent Payments */}
      <div className="card">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg font-semibold text-gray-900">Recent Payments</h2>
          <Link href="/dashboard/payments" className="text-primary-600 text-sm hover:underline">
            View all →
          </Link>
        </div>
        {safeRecentPayments.length > 0 ? (
          <PaymentsTable payments={safeRecentPayments} />
        ) : (
          <p className="text-gray-500 text-center py-8">No payments yet</p>
        )}
      </div>
    </div>
  )
}
