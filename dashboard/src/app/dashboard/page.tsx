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
        <p className="text-red-500 mb-4">Failed to load dashboard data</p>
        <button onClick={() => window.location.reload()} className="btn-primary">
          Retry
        </button>
      </div>
    )
  }

  const { stats, recent_payments, pending_payments } = data!

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
          value={stats.confirmed_count.toLocaleString()}
          icon={<HiOutlineCheck className="h-6 w-6" />}
          color="green"
        />
        <StatsCard
          title="Pending Payments"
          value={stats.pending_count.toLocaleString()}
          icon={<HiOutlineClock className="h-6 w-6" />}
          color="yellow"
        />
        <StatsCard
          title="Total Confirmed"
          value={formatCurrency(stats.total_confirmed)}
          icon={<HiOutlineCash className="h-6 w-6" />}
          color="blue"
        />
        <StatsCard
          title="Today's Revenue"
          value={formatCurrency(stats.today_amount)}
          icon={<HiOutlineCalendar className="h-6 w-6" />}
          color="purple"
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Payment Methods Chart */}
        <div className="card">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Payment Methods</h2>
          {stats.by_method && Object.keys(stats.by_method).length > 0 ? (
            <MethodsChart data={stats.by_method} />
          ) : (
            <p className="text-gray-500 text-center py-8">No data yet</p>
          )}
        </div>

        {/* Pending Payments */}
        <div className="lg:col-span-2 card">
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-lg font-semibold text-gray-900">Pending Verification</h2>
            {pending_payments.length > 0 && (
              <Link href="/dashboard/payments?status=pending" className="text-primary-600 text-sm hover:underline">
                View all →
              </Link>
            )}
          </div>
          {pending_payments.length > 0 ? (
            <PaymentsTable payments={pending_payments.slice(0, 5)} />
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
        <PaymentsTable payments={recent_payments} />
      </div>
    </div>
  )
}
