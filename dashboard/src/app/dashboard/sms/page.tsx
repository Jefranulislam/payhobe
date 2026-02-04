'use client'

import { useQuery } from '@tanstack/react-query'
import { apiClient } from '@/lib/api'
import { MethodBadge } from '@/components/Badge'
import { formatDate, timeAgo } from '@/lib/utils'
import { HiRefresh } from 'react-icons/hi'
import Link from 'next/link'

export default function SmsLogsPage() {
  const { data, isLoading, refetch } = useQuery({
    queryKey: ['sms-logs'],
    queryFn: () => apiClient.getSmsLogs(),
  })

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">SMS Logs</h1>
          <p className="text-gray-500 mt-1">View SMS messages received for payment verification</p>
        </div>
        <button onClick={() => refetch()} className="btn-secondary flex items-center gap-2">
          <HiRefresh className="h-4 w-4" />
          Refresh
        </button>
      </div>

      {/* Webhook Info */}
      <div className="card bg-blue-50 border-blue-200">
        <h3 className="font-medium text-blue-900 mb-2">SMS Webhook URL</h3>
        <code className="block bg-white px-4 py-2 rounded border border-blue-200 text-sm">
          {process.env.NEXT_PUBLIC_API_URL}/sms/receive
        </code>
        <p className="text-blue-700 text-sm mt-2">
          Use this URL in your SMS Forwarder app to send messages to PayHobe.
        </p>
      </div>

      {/* Logs Table */}
      <div className="card">
        {isLoading ? (
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
          </div>
        ) : data?.logs?.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-gray-200">
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    ID
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Sender
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Method
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Parsed Data
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Status
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Received
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {data.logs.map((log: any) => (
                  <tr key={log.sms_id} className="hover:bg-gray-50">
                    <td className="px-4 py-4 text-sm font-medium">{log.sms_id}</td>
                    <td className="px-4 py-4">
                      <code className="text-sm bg-gray-100 px-2 py-1 rounded">
                        {log.sender_number || '—'}
                      </code>
                    </td>
                    <td className="px-4 py-4">
                      {log.payment_method && log.payment_method !== 'unknown' ? (
                        <MethodBadge method={log.payment_method} />
                      ) : (
                        <span className="text-gray-400">—</span>
                      )}
                    </td>
                    <td className="px-4 py-4 text-sm">
                      {log.parsed_transaction_id && (
                        <div>
                          <strong>TrxID:</strong>{' '}
                          <code className="bg-gray-100 px-1 rounded">{log.parsed_transaction_id}</code>
                        </div>
                      )}
                      {log.parsed_amount && (
                        <div>
                          <strong>Amount:</strong> ৳{log.parsed_amount}
                        </div>
                      )}
                    </td>
                    <td className="px-4 py-4">
                      {log.is_processed ? (
                        <span className="badge-confirmed">
                          Processed
                          {log.matched_payment_id && (
                            <Link
                              href={`/dashboard/payments/${log.matched_payment_id}`}
                              className="ml-1 underline"
                            >
                              #{log.matched_payment_id}
                            </Link>
                          )}
                        </span>
                      ) : (
                        <span className="badge-pending">Pending</span>
                      )}
                    </td>
                    <td className="px-4 py-4 text-sm text-gray-500">
                      <div title={formatDate(log.received_at)}>{timeAgo(log.received_at)}</div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="text-center py-12">
            <div className="text-6xl mb-4">📱</div>
            <h3 className="text-lg font-medium text-gray-900 mb-2">No SMS logs yet</h3>
            <p className="text-gray-500 max-w-md mx-auto">
              SMS messages from your MFS accounts will appear here once you configure SMS forwarding.
            </p>
          </div>
        )}
      </div>
    </div>
  )
}
