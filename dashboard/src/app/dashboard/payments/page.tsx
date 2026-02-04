'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/lib/api'
import { PaymentsTable } from '@/components/PaymentsTable'
import { HiSearch, HiRefresh, HiDownload } from 'react-icons/hi'
import toast from 'react-hot-toast'

export default function PaymentsPage() {
  const queryClient = useQueryClient()
  const [filters, setFilters] = useState({
    page: 1,
    per_page: 25,
    status: '',
    method: '',
    search: '',
  })

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['payments', filters],
    queryFn: () => apiClient.getPayments(filters),
  })

  const verifyMutation = useMutation({
    mutationFn: ({ id, action, notes }: { id: number; action: 'confirm' | 'reject'; notes?: string }) =>
      apiClient.verifyPayment(id, action, notes),
    onSuccess: () => {
      toast.success('Payment updated successfully')
      queryClient.invalidateQueries({ queryKey: ['payments'] })
      queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: () => {
      toast.error('Failed to update payment')
    },
  })

  const handleVerify = (id: number, action: 'confirm' | 'reject') => {
    if (window.confirm(`Are you sure you want to ${action} this payment?`)) {
      verifyMutation.mutate({ id, action })
    }
  }

  const handleFilterChange = (key: string, value: string) => {
    setFilters((prev) => ({ ...prev, [key]: value, page: 1 }))
  }

  const handleSearch = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    refetch()
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Payments</h1>
          <p className="text-gray-500 mt-1">Manage and verify customer payments</p>
        </div>
        <div className="flex gap-2">
          <button onClick={() => refetch()} className="btn-secondary flex items-center gap-2">
            <HiRefresh className="h-4 w-4" />
            Refresh
          </button>
          <button className="btn-secondary flex items-center gap-2">
            <HiDownload className="h-4 w-4" />
            Export
          </button>
        </div>
      </div>

      {/* Filters */}
      <div className="card">
        <form onSubmit={handleSearch} className="flex flex-wrap gap-4">
          <div>
            <select
              value={filters.status}
              onChange={(e) => handleFilterChange('status', e.target.value)}
              className="input"
            >
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="failed">Failed</option>
            </select>
          </div>
          <div>
            <select
              value={filters.method}
              onChange={(e) => handleFilterChange('method', e.target.value)}
              className="input"
            >
              <option value="">All Methods</option>
              <option value="bkash">bKash</option>
              <option value="nagad">Nagad</option>
              <option value="rocket">Rocket</option>
              <option value="upay">Upay</option>
              <option value="bank">Bank</option>
            </select>
          </div>
          <div className="flex-1 min-w-[200px]">
            <div className="relative">
              <HiSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-5 w-5" />
              <input
                type="text"
                placeholder="Search transaction ID..."
                value={filters.search}
                onChange={(e) => handleFilterChange('search', e.target.value)}
                className="input pl-10"
              />
            </div>
          </div>
          <button type="submit" className="btn-primary">
            Search
          </button>
        </form>
      </div>

      {/* Table */}
      <div className="card">
        {isLoading ? (
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
          </div>
        ) : (
          <>
            <PaymentsTable
              payments={data?.payments || []}
              showActions
              onVerify={handleVerify}
            />

            {/* Pagination */}
            {data && data.total_pages > 1 && (
              <div className="flex justify-between items-center mt-6 pt-6 border-t">
                <p className="text-sm text-gray-500">
                  Showing {(filters.page - 1) * filters.per_page + 1} to{' '}
                  {Math.min(filters.page * filters.per_page, data.total)} of {data.total} payments
                </p>
                <div className="flex gap-2">
                  <button
                    onClick={() => setFilters((prev) => ({ ...prev, page: prev.page - 1 }))}
                    disabled={filters.page === 1}
                    className="btn-secondary"
                  >
                    Previous
                  </button>
                  <button
                    onClick={() => setFilters((prev) => ({ ...prev, page: prev.page + 1 }))}
                    disabled={filters.page >= data.total_pages}
                    className="btn-secondary"
                  >
                    Next
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  )
}
