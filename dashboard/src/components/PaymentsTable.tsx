'use client'

import Link from 'next/link'
import { Payment } from '@/lib/api'
import { MethodBadge, StatusBadge } from './Badge'
import { formatCurrency, formatDate, timeAgo } from '@/lib/utils'

interface PaymentsTableProps {
  payments: Payment[]
  showActions?: boolean
  onVerify?: (id: number, action: 'confirm' | 'reject') => void
}

export function PaymentsTable({ payments, showActions, onVerify }: PaymentsTableProps) {
  if (payments.length === 0) {
    return (
      <div className="text-center py-12 text-gray-500">
        <p>No payments found</p>
      </div>
    )
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full">
        <thead>
          <tr className="border-b border-gray-200">
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              ID
            </th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Method
            </th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Transaction
            </th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Amount
            </th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Status
            </th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Customer
            </th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Date
            </th>
            {showActions && (
              <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                Actions
              </th>
            )}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200">
          {payments.map((payment) => (
            <tr key={payment.payment_id} className="hover:bg-gray-50">
              <td className="px-4 py-4 whitespace-nowrap">
                <Link
                  href={`/dashboard/payments/${payment.payment_id}`}
                  className="text-primary-600 hover:text-primary-700 font-medium"
                >
                  #{payment.payment_id}
                </Link>
              </td>
              <td className="px-4 py-4 whitespace-nowrap">
                <MethodBadge method={payment.payment_method} />
              </td>
              <td className="px-4 py-4 whitespace-nowrap">
                <code className="text-sm bg-gray-100 px-2 py-1 rounded">
                  {payment.transaction_id || '—'}
                </code>
              </td>
              <td className="px-4 py-4 whitespace-nowrap font-medium">
                {formatCurrency(payment.amount)}
              </td>
              <td className="px-4 py-4 whitespace-nowrap">
                <StatusBadge status={payment.payment_status} />
              </td>
              <td className="px-4 py-4 whitespace-nowrap">
                <div className="text-sm">
                  <div className="text-gray-900">{payment.customer_name || '—'}</div>
                  <div className="text-gray-500 text-xs">{payment.customer_email}</div>
                </div>
              </td>
              <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                <div title={formatDate(payment.created_at)}>{timeAgo(payment.created_at)}</div>
              </td>
              {showActions && payment.payment_status === 'pending' && (
                <td className="px-4 py-4 whitespace-nowrap text-right">
                  <button
                    onClick={() => onVerify?.(payment.payment_id, 'confirm')}
                    className="btn-success text-xs px-3 py-1.5 mr-2"
                  >
                    Confirm
                  </button>
                  <button
                    onClick={() => onVerify?.(payment.payment_id, 'reject')}
                    className="btn-danger text-xs px-3 py-1.5"
                  >
                    Reject
                  </button>
                </td>
              )}
              {showActions && payment.payment_status !== 'pending' && (
                <td className="px-4 py-4 whitespace-nowrap text-right">
                  <Link
                    href={`/dashboard/payments/${payment.payment_id}`}
                    className="btn-secondary text-xs px-3 py-1.5"
                  >
                    View
                  </Link>
                </td>
              )}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
