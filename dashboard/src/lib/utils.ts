import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'
import { format, formatDistanceToNow } from 'date-fns'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatCurrency(amount: number | string, currency = 'BDT'): string {
  const num = typeof amount === 'string' ? parseFloat(amount) : amount
  return `৳${num.toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

export function formatDate(date: string): string {
  return format(new Date(date), 'MMM d, yyyy h:mm a')
}

export function formatDateShort(date: string): string {
  return format(new Date(date), 'MMM d, yyyy')
}

export function timeAgo(date: string): string {
  return formatDistanceToNow(new Date(date), { addSuffix: true })
}

export function getMethodColor(method: string): string {
  const colors: Record<string, string> = {
    bkash: '#E2136E',
    nagad: '#F6921E',
    rocket: '#8B1D82',
    upay: '#00A0E3',
    bank: '#333333',
  }
  return colors[method] || '#666666'
}

export function getStatusColor(status: string): { bg: string; text: string } {
  const colors: Record<string, { bg: string; text: string }> = {
    pending: { bg: 'bg-yellow-100', text: 'text-yellow-800' },
    confirmed: { bg: 'bg-green-100', text: 'text-green-800' },
    failed: { bg: 'bg-red-100', text: 'text-red-800' },
  }
  return colors[status] || { bg: 'bg-gray-100', text: 'text-gray-800' }
}

export function maskPhone(phone: string): string {
  if (!phone || phone.length < 6) return phone
  return phone.substring(0, 3) + '****' + phone.substring(phone.length - 4)
}
