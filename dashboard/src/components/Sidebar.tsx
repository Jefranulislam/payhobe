'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'
import {
  HiOutlineHome,
  HiOutlineCreditCard,
  HiOutlineCog,
  HiOutlineChat,
  HiOutlineLogout,
} from 'react-icons/hi'

const navigation = [
  { name: 'Dashboard', href: '/dashboard', icon: HiOutlineHome },
  { name: 'Payments', href: '/dashboard/payments', icon: HiOutlineCreditCard },
  { name: 'SMS Logs', href: '/dashboard/sms', icon: HiOutlineChat },
  { name: 'Settings', href: '/dashboard/settings', icon: HiOutlineCog },
]

export function Sidebar() {
  const pathname = usePathname()

  const handleLogout = () => {
    localStorage.removeItem('payhobe_token')
    window.location.href = '/login'
  }

  return (
    <aside className="fixed left-0 top-0 z-40 h-screen w-64 bg-gray-900">
      <div className="flex h-full flex-col">
        {/* Logo */}
        <div className="flex h-16 items-center justify-center border-b border-gray-800">
          <span className="text-2xl font-bold text-white">Pay<span className="text-primary-400">Hobe</span></span>
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
            Logout
          </button>
        </div>
      </div>
    </aside>
  )
}
