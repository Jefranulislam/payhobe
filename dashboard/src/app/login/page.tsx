'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import toast from 'react-hot-toast'
import { HiLockClosed, HiLink } from 'react-icons/hi'

export default function LoginPage() {
  const router = useRouter()
  const [apiUrl, setApiUrl] = useState('')
  const [apiToken, setApiToken] = useState('')
  const [isLoading, setIsLoading] = useState(false)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsLoading(true)

    try {
      // Clean up the API URL - remove trailing slash
      const cleanApiUrl = apiUrl.replace(/\/+$/, '')
      
      // Validate the connection
      const response = await fetch(`${cleanApiUrl}/ping`, {
        headers: {
          'X-PayHobe-Token': apiToken,
        },
      })

      if (!response.ok) {
        throw new Error('Invalid credentials')
      }

      // Store credentials
      localStorage.setItem('payhobe_api_url', cleanApiUrl)
      localStorage.setItem('payhobe_api_token', apiToken)

      toast.success('Connected successfully!')
      router.push('/dashboard')
    } catch (error) {
      console.error('Connection error:', error)
      toast.error('Failed to connect. Please check your API URL and token.')
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-primary-600 to-primary-800 flex items-center justify-center p-4">
      <div className="w-full max-w-md">
        {/* Logo */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/10 mb-4">
            <span className="text-3xl">💳</span>
          </div>
          <h1 className="text-3xl font-bold text-white">PayHobe</h1>
          <p className="text-primary-200 mt-2">Bangladeshi MFS Payment Gateway</p>
        </div>

        {/* Login Card */}
        <div className="bg-white rounded-2xl shadow-xl p-8">
          <h2 className="text-xl font-semibold text-gray-900 mb-6">Connect to WordPress</h2>

          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label htmlFor="apiUrl" className="block text-sm font-medium text-gray-700 mb-1">
                API URL
              </label>
              <div className="relative">
                <HiLink className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 h-5 w-5" />
                <input
                  type="url"
                  id="apiUrl"
                  value={apiUrl}
                  onChange={(e) => setApiUrl(e.target.value)}
                  placeholder="https://yoursite.com/wp-json/payhobe/v1"
                  className="input pl-10"
                  required
                />
              </div>
              <p className="text-xs text-gray-500 mt-1">
                Your WordPress site URL + /wp-json/payhobe/v1
              </p>
            </div>

            <div>
              <label htmlFor="apiToken" className="block text-sm font-medium text-gray-700 mb-1">
                API Token
              </label>
              <div className="relative">
                <HiLockClosed className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 h-5 w-5" />
                <input
                  type="password"
                  id="apiToken"
                  value={apiToken}
                  onChange={(e) => setApiToken(e.target.value)}
                  placeholder="Your API token"
                  className="input pl-10"
                  required
                />
              </div>
              <p className="text-xs text-gray-500 mt-1">
                Generate this in WordPress Admin → PayHobe → Settings → API
              </p>
            </div>

            <button type="submit" disabled={isLoading} className="btn-primary w-full py-3">
              {isLoading ? (
                <span className="flex items-center justify-center gap-2">
                  <svg
                    className="animate-spin h-5 w-5"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                  >
                    <circle
                      className="opacity-25"
                      cx="12"
                      cy="12"
                      r="10"
                      stroke="currentColor"
                      strokeWidth="4"
                    ></circle>
                    <path
                      className="opacity-75"
                      fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                  </svg>
                  Connecting...
                </span>
              ) : (
                'Connect'
              )}
            </button>
          </form>
        </div>

        {/* Help Text */}
        <div className="text-center mt-6">
          <p className="text-primary-200 text-sm">
            Need help?{' '}
            <a
              href="https://github.com/your-repo/payhobe#readme"
              target="_blank"
              rel="noopener noreferrer"
              className="text-white underline hover:no-underline"
            >
              View Documentation
            </a>
          </p>
        </div>
      </div>
    </div>
  )
}
