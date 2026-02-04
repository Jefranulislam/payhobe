'use client'

import { useState, useEffect } from 'react'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'
import {
  HiOutlineHome,
  HiOutlineCreditCard,
  HiOutlineCog,
  HiOutlineChat,
  HiOutlineLogout,
  HiOutlineStatusOnline,
  HiOutlineStatusOffline,
} from 'react-icons/hi'

const navigation = [
  { name: 'Dashboard', href: '/dashboard', icon: HiOutlineHome },
  { name: 'Payments', href: '/dashboard/payments', icon: HiOutlineCreditCard },
  { name: 'SMS Logs', href: '/dashboard/sms', icon: HiOutlineChat },
  { name: 'Settings', href: '/dashboard/settings', icon: HiOutlineCog },
]

export function Sidebar() {
  const pathname = usePathname()
  const [apiUrl, setApiUrl] = useState<string | null>(null)
  const [isConnected, setIsConnected] = useState(false)

  useEffect(() => {
    const storedUrl = localStorage.getItem('payhobe_api_url')
    setApiUrl(storedUrl)
    
    // Test connection
    if (storedUrl) {
      const token = localStorage.getItem('payhobe_api_token')
      fetch(`${storedUrl}/ping`, {
        headers: token ? { 'X-PayHobe-Token': token } : {},
      })
        .then(res => setIsConnected(res.ok))
        .catch(() => setIsConnected(false))
    }
  }, [])

  const handleLogout = () => {
    localStorage.removeItem('payhobe_api_url')
    localStorage.removeItem('payhobe_api_token')
    window.location.href = '/login'
  }

  // Extract domain from API URL for display
  const displayUrl = apiUrl ? (() => {
    try {
      return new URL(apiUrl).hostname
    } catch {
      return apiUrl
    }
  })() : 'Not connected'

  return (
    <aside className="fixed left-0 top-0 z-40 h-screen w-64 bg-gray-900">
      <div className="flex h-full flex-col">
        {/* Logo */}
        <div className="flex h-16 items-center justify-center border-b border-gray-800">
          <span className="text-2xl font-bold text-white">Pay<span className="text-primary-400">Hobe</span></span>
        </div>

        {/* Connection Status */}
        <div className="px-3 py-3 border-b border-gray-800">
          <div className="flex items-center gap-2 text-xs">
            {isConnected ? (
              <HiOutlineStatusOnline className="h-4 w-4 text-green-400" />
            ) : (
              <HiOutlineStatusOffline className="h-4 w-4 text-red-400" />
            )}
            <span className={isConnected ? 'text-green-400' : 'text-red-400'}>
              {isConnected ? 'Connected' : 'Disconnected'}
            </span>
          </div>
          <p className="text-gray-500 text-xs mt-1 truncate" title={apiUrl || ''}>
            {displayUrl}
          </p>
        </div>

        {/* Navigation */}
        <nav className="flex-1 space-y-1 px-3 py-4">
          {navigation.map((item) => {
            const isActive = pathname === item.href || 
              (item.href !== '/dashboard' && pathname.startsWith(item.href))
            
            return (
              <Link
                key={item.name}
                href={item.href}
                className={cn(
                  'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                  isActive
                    ? 'bg-primary-600 text-white'
                    : 'text-gray-300 hover:bg-gray-800 hover:text-white'
                )}
              >
                <item.icon className="h-5 w-5" />
                {item.name}
              </Link>
            )
          })}
        </nav>

        {/* Logout */}
        <div className="border-t border-gray-800 p-3">
          <button
            onClick={handleLogout}
            className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white transition-colors"
          >
            <HiOutlineLogout className="h-5 w-5" />
            Disconnect
          </button>
        </div>
      </div>
    </aside>
  )
}
