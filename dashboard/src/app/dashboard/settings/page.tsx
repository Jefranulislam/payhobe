'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient, type MfsConfig } from '@/lib/api'
import { MethodBadge } from '@/components/Badge'
import toast from 'react-hot-toast'
import { HiPlus, HiPencil, HiTrash, HiEye, HiEyeOff, HiSave } from 'react-icons/hi'

interface MfsFormData {
  provider: string
  account_type: 'personal' | 'merchant' | 'agent'
  account_number: string
  account_name: string
  is_active: boolean
}

const MFS_PROVIDERS = ['bkash', 'nagad', 'rocket', 'upay']
const ACCOUNT_TYPES = ['personal', 'merchant', 'agent']

export default function SettingsPage() {
  const queryClient = useQueryClient()
  const [activeTab, setActiveTab] = useState<'mfs' | 'general' | 'api'>('mfs')
  const [showAccountNumber, setShowAccountNumber] = useState<Record<number, boolean>>({})
  const [editingMfs, setEditingMfs] = useState<MfsFormData | null>(null)
  const [editingMfsId, setEditingMfsId] = useState<number | null>(null)

  // MFS Configs Query
  const { data: mfsData, isLoading: mfsLoading } = useQuery({
    queryKey: ['mfs-configs'],
    queryFn: () => apiClient.getMfsConfigs(),
    enabled: activeTab === 'mfs',
  })

  // MFS Mutations
  const createMfsMutation = useMutation({
    mutationFn: (data: MfsFormData) => apiClient.createMfsConfig(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['mfs-configs'] })
      toast.success('MFS account added successfully')
      setEditingMfs(null)
    },
    onError: () => toast.error('Failed to add MFS account'),
  })

  const updateMfsMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: MfsFormData }) =>
      apiClient.updateMfsConfig(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['mfs-configs'] })
      toast.success('MFS account updated successfully')
      setEditingMfs(null)
      setEditingMfsId(null)
    },
    onError: () => toast.error('Failed to update MFS account'),
  })

  const deleteMfsMutation = useMutation({
    mutationFn: (id: number) => apiClient.deleteMfsConfig(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['mfs-configs'] })
      toast.success('MFS account deleted')
    },
    onError: () => toast.error('Failed to delete MFS account'),
  })

  const handleMfsSave = () => {
    if (!editingMfs) return
    if (editingMfsId) {
      updateMfsMutation.mutate({ id: editingMfsId, data: editingMfs })
    } else {
      createMfsMutation.mutate(editingMfs)
    }
  }

  const startEditingMfs = (config: MfsConfig) => {
    setEditingMfsId(config.config_id)
    setEditingMfs({
      provider: config.provider,
      account_type: config.account_type,
      account_number: config.account_number || '',
      account_name: config.account_name,
      is_active: config.is_active,
    })
  }

  const startNewMfs = () => {
    setEditingMfsId(null)
    setEditingMfs({
      provider: 'bkash',
      account_type: 'personal',
      account_number: '',
      account_name: '',
      is_active: true,
    })
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Settings</h1>
        <p className="text-gray-500 mt-1">Manage your PayHobe configuration</p>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200">
        <nav className="flex gap-8">
          {[
            { id: 'mfs', label: 'MFS Accounts' },
            { id: 'general', label: 'General Settings' },
            { id: 'api', label: 'API Settings' },
          ].map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={`py-3 px-1 border-b-2 text-sm font-medium ${
                activeTab === tab.id
                  ? 'border-primary-500 text-primary-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </nav>
      </div>

      {/* MFS Accounts Tab */}
      {activeTab === 'mfs' && (
        <div className="space-y-6">
          {/* Add/Edit Form */}
          {editingMfs ? (
            <div className="card">
              <h3 className="text-lg font-medium mb-4">
                {editingMfsId ? 'Edit MFS Account' : 'Add New MFS Account'}
              </h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Provider</label>
                  <select
                    value={editingMfs.provider}
                    onChange={(e) => setEditingMfs({ ...editingMfs, provider: e.target.value })}
                    className="input"
                    disabled={!!editingMfsId}
                  >
                    {MFS_PROVIDERS.map((p) => (
                      <option key={p} value={p}>
                        {p.charAt(0).toUpperCase() + p.slice(1)}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Account Type
                  </label>
                  <select
                    value={editingMfs.account_type}
                    onChange={(e) =>
                      setEditingMfs({ ...editingMfs, account_type: e.target.value as any })
                    }
                    className="input"
                  >
                    {ACCOUNT_TYPES.map((t) => (
                      <option key={t} value={t}>
                        {t.charAt(0).toUpperCase() + t.slice(1)}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Account Number
                  </label>
                  <input
                    type="text"
                    value={editingMfs.account_number}
                    onChange={(e) =>
                      setEditingMfs({ ...editingMfs, account_number: e.target.value })
                    }
                    className="input"
                    placeholder="01XXXXXXXXX"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Account Name
                  </label>
                  <input
                    type="text"
                    value={editingMfs.account_name}
                    onChange={(e) => setEditingMfs({ ...editingMfs, account_name: e.target.value })}
                    className="input"
                    placeholder="Account holder name"
                  />
                </div>
                <div className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    id="is_active"
                    checked={editingMfs.is_active}
                    onChange={(e) => setEditingMfs({ ...editingMfs, is_active: e.target.checked })}
                    className="w-4 h-4 rounded border-gray-300 text-primary-600"
                  />
                  <label htmlFor="is_active" className="text-sm text-gray-700">
                    Active
                  </label>
                </div>
              </div>
              <div className="flex gap-3 mt-6">
                <button
                  onClick={handleMfsSave}
                  disabled={createMfsMutation.isPending || updateMfsMutation.isPending}
                  className="btn-primary flex items-center gap-2"
                >
                  <HiSave className="h-4 w-4" />
                  Save
                </button>
                <button
                  onClick={() => {
                    setEditingMfs(null)
                    setEditingMfsId(null)
                  }}
                  className="btn-secondary"
                >
                  Cancel
                </button>
              </div>
            </div>
          ) : (
            <button onClick={startNewMfs} className="btn-primary flex items-center gap-2">
              <HiPlus className="h-4 w-4" />
              Add MFS Account
            </button>
          )}

          {/* MFS List */}
          <div className="card">
            {mfsLoading ? (
              <div className="flex items-center justify-center h-32">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
              </div>
            ) : (mfsData?.configs?.length ?? 0) > 0 ? (
              <div className="divide-y divide-gray-200">
                {mfsData.configs.map((config: MfsConfig) => (
                  <div
                    key={config.config_id}
                    className="py-4 flex items-center justify-between gap-4"
                  >
                    <div className="flex items-center gap-4">
                      <MethodBadge method={config.provider} />
                      <div>
                        <div className="font-medium flex items-center gap-2">
                          {showAccountNumber[config.config_id]
                            ? config.account_number
                            : config.account_number?.replace(/./g, '•')}
                          <button
                            onClick={() =>
                              setShowAccountNumber((prev) => ({
                                ...prev,
                                [config.config_id]: !prev[config.config_id],
                              }))
                            }
                            className="text-gray-400 hover:text-gray-600"
                          >
                            {showAccountNumber[config.config_id] ? (
                              <HiEyeOff className="h-4 w-4" />
                            ) : (
                              <HiEye className="h-4 w-4" />
                            )}
                          </button>
                        </div>
                        <div className="text-sm text-gray-500">
                          {config.account_name} • {config.account_type}
                        </div>
                      </div>
                    </div>
                    <div className="flex items-center gap-3">
                      <span
                        className={`text-xs px-2 py-1 rounded ${
                          config.is_active
                            ? 'bg-green-100 text-green-800'
                            : 'bg-gray-100 text-gray-600'
                        }`}
                      >
                        {config.is_active ? 'Active' : 'Inactive'}
                      </span>
                      <button
                        onClick={() => startEditingMfs(config)}
                        className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded"
                      >
                        <HiPencil className="h-4 w-4" />
                      </button>
                      <button
                        onClick={() => {
                          if (confirm('Delete this MFS account?')) {
                            deleteMfsMutation.mutate(config.config_id)
                          }
                        }}
                        className="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded"
                      >
                        <HiTrash className="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center py-8">
                <div className="text-4xl mb-2">📱</div>
                <p className="text-gray-500">No MFS accounts configured yet</p>
              </div>
            )}
          </div>
        </div>
      )}

      {/* General Settings Tab */}
      {activeTab === 'general' && (
        <div className="card">
          <h3 className="text-lg font-medium mb-4">General Settings</h3>
          <p className="text-gray-500">
            General settings are managed through the WordPress admin panel.
          </p>
          <a
            href={`${process.env.NEXT_PUBLIC_API_URL?.replace('/wp-json/payhobe/v1', '')}/wp-admin/admin.php?page=payhobe-settings`}
            target="_blank"
            rel="noopener noreferrer"
            className="btn-primary mt-4 inline-block"
          >
            Open WordPress Settings
          </a>
        </div>
      )}

      {/* API Settings Tab */}
      {activeTab === 'api' && (
        <div className="space-y-6">
          <div className="card">
            <h3 className="text-lg font-medium mb-4">API Configuration</h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">API Base URL</label>
                <code className="block bg-gray-100 px-4 py-2 rounded text-sm">
                  {process.env.NEXT_PUBLIC_API_URL}
                </code>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  SMS Webhook URL
                </label>
                <code className="block bg-gray-100 px-4 py-2 rounded text-sm">
                  {process.env.NEXT_PUBLIC_API_URL}/sms/receive
                </code>
              </div>
            </div>
          </div>

          <div className="card bg-yellow-50 border-yellow-200">
            <h3 className="font-medium text-yellow-900 mb-2">API Token Management</h3>
            <p className="text-yellow-700 text-sm">
              API tokens are managed through the WordPress admin panel for security reasons.
            </p>
            <a
              href={`${process.env.NEXT_PUBLIC_API_URL?.replace('/wp-json/payhobe/v1', '')}/wp-admin/admin.php?page=payhobe-settings&tab=api`}
              target="_blank"
              rel="noopener noreferrer"
              className="btn-secondary mt-4 inline-block"
            >
              Manage API Tokens
            </a>
          </div>
        </div>
      )}
    </div>
  )
}
