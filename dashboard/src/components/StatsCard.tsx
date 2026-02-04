'use client'

import { cn } from '@/lib/utils'
import { ReactNode } from 'react'

interface StatsCardProps {
  title: string
  value: string | number
  icon: ReactNode
  trend?: {
    value: number
    isPositive: boolean
  }
  color?: 'blue' | 'green' | 'yellow' | 'purple' | 'red'
  className?: string
}

const colorClasses = {
  blue: 'bg-blue-500',
  green: 'bg-green-500',
  yellow: 'bg-yellow-500',
  purple: 'bg-purple-500',
  red: 'bg-red-500',
}

export function StatsCard({
  title,
  value,
  icon,
  trend,
  color = 'blue',
  className,
}: StatsCardProps) {
  return (
    <div className={cn('card', className)}>
      <div className="flex items-center gap-4">
        <div className={cn('flex-shrink-0 p-3 rounded-lg text-white', colorClasses[color])}>
          {icon}
        </div>
        <div className="flex-1 min-w-0">
          <p className="text-sm text-gray-500 truncate">{title}</p>
          <p className="text-2xl font-semibold text-gray-900">{value}</p>
          {trend && (
            <p className={cn('text-xs mt-1', trend.isPositive ? 'text-green-600' : 'text-red-600')}>
              {trend.isPositive ? '↑' : '↓'} {Math.abs(trend.value)}% from last period
            </p>
          )}
        </div>
      </div>
    </div>
  )
}
