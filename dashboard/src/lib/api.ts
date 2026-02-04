import axios from 'axios'

// Get base URL from localStorage or environment variable
const getBaseUrl = () => {
  if (typeof window !== 'undefined') {
    return localStorage.getItem('payhobe_api_url') || process.env.NEXT_PUBLIC_API_URL || ''
  }
  return process.env.NEXT_PUBLIC_API_URL || ''
}

// Get token from localStorage or environment variable
const getToken = () => {
  if (typeof window !== 'undefined') {
    return localStorage.getItem('payhobe_api_token') || process.env.NEXT_PUBLIC_API_TOKEN || ''
  }
  return process.env.NEXT_PUBLIC_API_TOKEN || ''
}

const api = axios.create({
  headers: {
    'Content-Type': 'application/json',
  },
})

// Set base URL dynamically before each request
api.interceptors.request.use((config) => {
  config.baseURL = getBaseUrl()
  
  const token = getToken()
  if (token) {
    config.headers['X-PayHobe-Token'] = token
  }
  return config
})

// Handle errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Redirect to login
      if (typeof window !== 'undefined') {
        localStorage.removeItem('payhobe_token')
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export interface Payment {
  payment_id: number
  order_id: number | null
  payment_method: string
  amount: string
  currency: string
  payment_status: string
  transaction_id: string | null
  sender_number_masked: string | null
  sender_account_type: string
  customer_name: string | null
  customer_email: string | null
  verification_source: string | null
  verified_at: string | null
  created_at: string
}

export interface PaymentsResponse {
  payments: Payment[]
  total: number
  page: number
  per_page: number
  total_pages: number
}

export interface DashboardStats {
  confirmed_count: number
  pending_count: number
  failed_count: number
  total_confirmed: number
  today_amount: number
  by_method: Record<string, { count: number; total: number }>
}

export interface DashboardOverview {
  stats: DashboardStats
  recent_payments: Payment[]
  pending_payments: Payment[]
}

export interface MfsConfig {
  config_id: number
  provider: string
  account_type: 'personal' | 'merchant' | 'agent'
  account_number: string
  account_name: string
  is_active: boolean
  created_at?: string
  updated_at?: string
}

// API Functions
export const apiClient = {
  // Auth
  login: async (username: string, password: string) => {
    const response = await api.post('/auth/login', { username, password })
    return response.data
  },

  me: async () => {
    const response = await api.get('/auth/me')
    return response.data
  },

  // Dashboard
  getDashboardOverview: async (): Promise<DashboardOverview> => {
    try {
      // Fetch overview data
      const overviewResponse = await api.get('/dashboard')
      const overview = overviewResponse.data?.data || overviewResponse.data || {}
      
      // Fetch pending payments
      const pendingResponse = await api.get('/dashboard/pending')
      const pendingPayments = pendingResponse.data?.data?.payments || pendingResponse.data?.payments || []
      
      // Fetch recent payments
      const recentResponse = await api.get('/dashboard/recent-payments')
      const recentPayments = recentResponse.data?.data?.payments || recentResponse.data?.payments || []
      
      // Transform the data to match the expected format
      return {
        stats: {
          confirmed_count: overview.total?.payments || 0,
          pending_count: overview.alerts?.pending_payments || 0,
          failed_count: 0,
          total_confirmed: overview.total?.amount || 0,
          today_amount: overview.today?.amount || 0,
          by_method: overview.by_method || {}
        },
        recent_payments: recentPayments,
        pending_payments: pendingPayments
      }
    } catch (error) {
      console.error('Dashboard overview error:', error)
      // Return empty data structure on error
      return {
        stats: {
          confirmed_count: 0,
          pending_count: 0,
          failed_count: 0,
          total_confirmed: 0,
          today_amount: 0,
          by_method: {}
        },
        recent_payments: [],
        pending_payments: []
      }
    }
  },

  getDashboardStats: async (period = 'month'): Promise<DashboardStats> => {
    const response = await api.get('/dashboard/stats', { params: { period } })
    return response.data
  },

  getChartData: async (period = 'week') => {
    const response = await api.get('/dashboard/chart', { params: { period } })
    return response.data
  },

  // Payments
  getPayments: async (params: {
    page?: number
    per_page?: number
    status?: string
    method?: string
    search?: string
  } = {}): Promise<PaymentsResponse> => {
    const response = await api.get('/payments', { params })
    return response.data
  },

  getPayment: async (id: number): Promise<Payment> => {
    const response = await api.get(`/payments/${id}`)
    return response.data
  },

  verifyPayment: async (id: number, action: 'confirm' | 'reject', notes?: string) => {
    const response = await api.post(`/payments/${id}/verify`, { action, notes })
    return response.data
  },

  getPaymentStats: async () => {
    const response = await api.get('/payments/stats')
    return response.data
  },

  // SMS
  getSmsLogs: async (params: { page?: number; method?: string; processed?: boolean } = {}) => {
    const response = await api.get('/sms/logs', { params })
    return response.data
  },

  // Config
  getConfig: async () => {
    const response = await api.get('/config')
    return response.data
  },

  updateConfig: async (method: string, data: any) => {
    const response = await api.post(`/config/${method}`, data)
    return response.data
  },

  // MFS Configs
  getMfsConfigs: async (): Promise<{ configs: MfsConfig[] }> => {
    const response = await api.get('/config/mfs')
    return response.data
  },

  createMfsConfig: async (data: Omit<MfsConfig, 'config_id' | 'created_at' | 'updated_at'>): Promise<MfsConfig> => {
    const response = await api.post('/config/mfs', data)
    return response.data
  },

  updateMfsConfig: async (config_id: number, data: Partial<MfsConfig>): Promise<MfsConfig> => {
    const response = await api.put(`/config/mfs/${config_id}`, data)
    return response.data
  },

  deleteMfsConfig: async (config_id: number): Promise<void> => {
    await api.delete(`/config/mfs/${config_id}`)
  },
}

export default api
