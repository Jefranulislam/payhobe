'use client'

import { createContext, useContext, useEffect, useState, ReactNode } from 'react'
import { useRouter, usePathname } from 'next/navigation'

interface AuthContextType {
  isAuthenticated: boolean
  apiUrl: string | null
  apiToken: string | null
  login: (url: string, token: string) => void
  logout: () => void
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
  const router = useRouter()
  const pathname = usePathname()
  const [isAuthenticated, setIsAuthenticated] = useState(false)
  const [apiUrl, setApiUrl] = useState<string | null>(null)
  const [apiToken, setApiToken] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    // Check localStorage for credentials
    const storedUrl = localStorage.getItem('payhobe_api_url')
    const storedToken = localStorage.getItem('payhobe_api_token')

    if (storedUrl && storedToken) {
      setApiUrl(storedUrl)
      setApiToken(storedToken)
      setIsAuthenticated(true)
    } else {
      setIsAuthenticated(false)
    }
    setIsLoading(false)
  }, [])

  useEffect(() => {
    if (!isLoading) {
      const isLoginPage = pathname === '/login'
      const isRootPage = pathname === '/'

      if (!isAuthenticated && !isLoginPage && !isRootPage) {
        router.replace('/login')
      }
    }
  }, [isAuthenticated, isLoading, pathname, router])

  const login = (url: string, token: string) => {
    localStorage.setItem('payhobe_api_url', url)
    localStorage.setItem('payhobe_api_token', token)
    setApiUrl(url)
    setApiToken(token)
    setIsAuthenticated(true)
  }

  const logout = () => {
    localStorage.removeItem('payhobe_api_url')
    localStorage.removeItem('payhobe_api_token')
    setApiUrl(null)
    setApiToken(null)
    setIsAuthenticated(false)
    router.replace('/login')
  }

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>
    )
  }

  return (
    <AuthContext.Provider value={{ isAuthenticated, apiUrl, apiToken, login, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}
