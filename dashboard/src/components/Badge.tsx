'use client'

import { cn, getStatusColor } from '@/lib/utils'

interface BadgeProps {
  children: React.ReactNode
  variant?: 'default' | 'success' | 'warning' | 'danger' | 'bkash' | 'nagad' | 'rocket' | 'upay' | 'bank'
  className?: string
}

const variantClasses: Record<string, string> = {
  default: 'bg-gray-100 text-gray-800',
  success: 'bg-green-100 text-green-800',
  warning: 'bg-yellow-100 text-yellow-800',
  danger: 'bg-red-100 text-red-800',
  bkash: 'bg-[#E2136E] text-white',
  nagad: 'bg-[#F6921E] text-white',
  rocket: 'bg-[#8B1D82] text-white',
  upay: 'bg-[#00A0E3] text-white',
  bank: 'bg-gray-700 text-white',
}

export function Badge({ children, variant = 'default', className }: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
        variantClasses[variant],
        className
      )}
    >
      {children}
    </span>
  )
}

interface StatusBadgeProps {
  status: string
  className?: string
}

export function StatusBadge({ status, className }: StatusBadgeProps) {
  const { bg, text } = getStatusColor(status)
  return (
    <span className={cn('badge', bg, text, className)}>
      {status.charAt(0).toUpperCase() + status.slice(1)}
    </span>
  )
}

interface MethodBadgeProps {
  method: string
  className?: string
}

export function MethodBadge({ method, className }: MethodBadgeProps) {
  const variant = method as keyof typeof variantClasses
  return (
    <Badge variant={variantClasses[variant] ? variant as any : 'default'} className={className}>
      {method.toUpperCase()}
    </Badge>
  )
}
