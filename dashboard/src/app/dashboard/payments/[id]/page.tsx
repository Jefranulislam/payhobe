'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useParams, useRouter } from 'next/navigation'
import { apiClient, Payment } from '@/lib/api'
import { MethodBadge, StatusBadge } from '@/components/Badge'
import { formatCurrency, formatDate } from '@/lib/utils'
import { HiArrowLeft, HiCheckCircle, HiXCircle } from 'react-icons/hi'
import toast from 'react-hot-toast'
import Link from 'next/link'

export default function PaymentDetailPage() {
  const params = useParams()
  const router = useRouter()
  const queryClient = useQueryClient()
  const paymentId = Number(params.id)

  const [notes, setNotes] = useState('')

  const { data: payment, isLoading } = useQuery({
    queryKey: ['payment', paymentId],
    queryFn: () => apiClient.getPayment(paymentId),
  })

  const verifyMutation = useMutation({
    mutationFn: ({ action }: { action: 'confirm' | 'reject' }) =>
      apiClient.verifyPayment(paymentId, action, notes),
    onSuccess: () => {
      toast.success('Payment updated successfully')
      queryClient.invalidateQueries({ queryKey: ['payment', paymentId] })
      queryClient.invalidateQueries({ queryKey: ['payments'] })
      queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: () => {
      toast.error('Failed to update payment')
    },
  })

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>
    )
  }

  if (!payment) {
    return (
      <div className="card text-center py-12">
        <p className="text-gray-500 mb-4">Payment not found</p>
        <Link href="/dashboard/payments" className="btn-primary">
          Back to Payments
        </Link>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <Link href="/dashboard/payments" className="text-gray-400 hover:text-gray-600">
          <HiArrowLeft className="h-6 w-6" />
        </Link>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Payment #{payment.payment_id}</h1>
          <p className="text-gray-500 mt-1">View and manage payment details</p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Info */}
        <div className="lg:col-span-2 space-y-6">
          <div className="card">
            <div className="flex justify-between items-start mb-6">
              <div className="flex items-center gap-3">
                <MethodBadge method={payment.payment_method} />
                <StatusBadge status={payment.payment_status} />
              </div>
              {payment.verification_source && (
                <span className="text-sm text-gray-500">
                  Verified via {payment.verification_source}
                </span>
              )}
            </div>

            <div className="text-center py-8 bg-gray-50 rounded-lg mb-6">
              <span className="text-gray-500 text-sm">Amount</span>
              <div className="text-4xl font-bold text-gray-900 mt-1">
                {formatCurrency(payment.amount)}
              </div>
            </div>

            <dl className="grid grid-cols-2 gap-4">
              <div>
                <dt className="text-sm text-gray-500">Transaction ID</dt>
                <dd className="mt-1 font-mono bg-gray-100 px-3 py-2 rounded">
                  {payment.transaction_id || '—'}
                </dd>
              </div>
              <div>
                <dt className="text-sm text-gray-500">Order ID</dt>
                <dd className="mt-1 font-medium">
                  {payment.order_id ? `#${payment.order_id}` : '—'}
                </dd>
              </div>
              <div>
                <dt className="text-sm text-gray-500">Sender Number</dt>
                <dd className="mt-1 font-medium">{payment.sender_number_masked || '—'}</dd>
              </div>
              <div>
                <dt className="text-sm text-gray-500">Account Type</dt>
                <dd className="mt-1 font-medium capitalize">{payment.sender_account_type}</dd>
              </div>
              <div>
                <dt className="text-sm text-gray-500">Created</dt>
                <dd className="mt-1 text-sm">{formatDate(payment.created_at)}</dd>
              </div>
              {payment.verified_at && (
                <div>
                  <dt className="text-sm text-gray-500">Verified</dt>
                  <dd className="mt-1 text-sm">{formatDate(payment.verified_at)}</dd>
                </div>
              )}
            </dl>
          </div>

          {/* Customer Info */}
          <div className="card">
            <h2 className="text-lg font-semibold mb-4">Customer Information</h2>
            <dl className="grid grid-cols-2 gap-4">
              <div>
                <dt className="text-sm text-gray-500">Name</dt>
                <dd className="mt-1 font-medium">{payment.customer_name || '—'}</dd>
              </div>
              <div>
                <dt className="text-sm text-gray-500">Email</dt>
                <dd className="mt-1">{payment.customer_email || '—'}</dd>
              </div>
            </dl>
          </div>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Actions */}
          {payment.payment_status === 'pending' && (
            <div className="card">
              <h2 className="text-lg font-semibold mb-4">Actions</h2>
              <div className="space-y-4">
                <div>
                  <label className="label">Notes (optional)</label>
                  <textarea
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    placeholder="Add verification notes..."
                    rows={3}
                    className="input"
                  />
                </div>
                <div className="flex gap-2">
                  <button
                    onClick={() => verifyMutation.mutate({ action: 'confirm' })}
                    disabled={verifyMutation.isPending}
                    className="btn-success flex-1 flex items-center justify-center gap-2"
                  >
                    <HiCheckCircle className="h-5 w-5" />
                    Confirm
                  </button>
                  <button
                    onClick={() => verifyMutation.mutate({ action: 'reject' })}
                    disabled={verifyMutation.isPending}
                    className="btn-danger flex-1 flex items-center justify-center gap-2"
                  >
                    <HiXCircle className="h-5 w-5" />
                    Reject
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* Status Card */}
          <div className="card">
            <h2 className="text-lg font-semibold mb-4">Status</h2>
            <div className="text-center py-6">
              {payment.payment_status === 'confirmed' && (
                <>
                  <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <HiCheckCircle className="h-10 w-10 text-green-600" />
                  </div>
                  <p className="text-green-600 font-medium">Payment Confirmed</p>
                </>
              )}
              {payment.payment_status === 'pending' && (
                <>
                  <div className="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <div className="animate-pulse w-8 h-8 bg-yellow-500 rounded-full"></div>
                  </div>
                  <p className="text-yellow-600 font-medium">Awaiting Verification</p>
                </>
              )}
              {payment.payment_status === 'failed' && (
                <>
                  <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <HiXCircle className="h-10 w-10 text-red-600" />
                  </div>
                  <p className="text-red-600 font-medium">Payment Failed</p>
                </>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
