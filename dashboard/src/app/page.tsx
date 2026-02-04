'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { 
  HiOutlineShieldCheck, 
  HiOutlineLightningBolt, 
  HiOutlineDeviceMobile,
  HiOutlineCash,
  HiOutlineChartBar,
  HiOutlineCog,
  HiOutlineCheck,
  HiOutlineArrowRight
} from 'react-icons/hi'

const features = [
  {
    icon: HiOutlineDeviceMobile,
    title: 'SMS Auto-Verification',
    description: 'Automatically verify payments through SMS forwarding from bKash, Nagad, Rocket, and Upay.',
  },
  {
    icon: HiOutlineLightningBolt,
    title: 'Instant Confirmation',
    description: 'Real-time payment verification and order confirmation for seamless customer experience.',
  },
  {
    icon: HiOutlineShieldCheck,
    title: 'Secure & Reliable',
    description: 'End-to-end encryption and secure API communication to protect your transactions.',
  },
  {
    icon: HiOutlineChartBar,
    title: 'Analytics Dashboard',
    description: 'Track payments, revenue, and trends with a beautiful real-time dashboard.',
  },
  {
    icon: HiOutlineCash,
    title: 'Multiple MFS Support',
    description: 'Accept payments via bKash, Nagad, Rocket, Upay, and bank transfers.',
  },
  {
    icon: HiOutlineCog,
    title: 'Easy Integration',
    description: 'Simple WordPress plugin with WooCommerce integration. No coding required.',
  },
]

const paymentMethods = [
  { name: 'bKash', color: 'bg-pink-500' },
  { name: 'Nagad', color: 'bg-orange-500' },
  { name: 'Rocket', color: 'bg-purple-600' },
  { name: 'Upay', color: 'bg-blue-500' },
  { name: 'Bank', color: 'bg-green-600' },
]

const pricingFeatures = [
  'Unlimited transactions',
  'All payment methods',
  'SMS auto-verification',
  'Real-time dashboard',
  'WooCommerce integration',
  'Priority support',
]

export default function LandingPage() {
  const [isLoggedIn, setIsLoggedIn] = useState(false)

  useEffect(() => {
    const apiUrl = localStorage.getItem('payhobe_api_url')
    const apiToken = localStorage.getItem('payhobe_api_token')
    setIsLoggedIn(!!(apiUrl && apiToken))
  }, [])

  return (
    <div className="min-h-screen bg-white">
      {/* Navigation */}
      <nav className="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-md border-b border-gray-100">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-16">
            <div className="flex items-center gap-2">
              <span className="text-2xl">💳</span>
              <span className="text-xl font-bold text-gray-900">Pay<span className="text-primary-600">Hobe</span></span>
            </div>
            <div className="flex items-center gap-4">
              {isLoggedIn ? (
                <Link href="/dashboard" className="btn-primary">
                  Go to Dashboard
                </Link>
              ) : (
                <>
                  <Link href="/login" className="text-gray-600 hover:text-gray-900 font-medium">
                    Connect Store
                  </Link>
                  <a 
                    href="https://github.com/Jefranulislam/payhobe" 
                    target="_blank" 
                    rel="noopener noreferrer"
                    className="btn-primary"
                  >
                    Get Started
                  </a>
                </>
              )}
            </div>
          </div>
        </div>
      </nav>

      {/* Hero Section */}
      <section className="pt-32 pb-20 px-4 sm:px-6 lg:px-8">
        <div className="max-w-7xl mx-auto">
          <div className="text-center max-w-4xl mx-auto">
            <div className="inline-flex items-center gap-2 bg-primary-50 text-primary-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
              <span className="animate-pulse">🔥</span>
              <span>The #1 MFS Payment Gateway for Bangladesh</span>
            </div>
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-bold text-gray-900 leading-tight mb-6">
              Accept <span className="text-primary-600">bKash, Nagad & More</span> on Your WooCommerce Store
            </h1>
            <p className="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
              Seamlessly integrate Bangladeshi mobile financial services into your WordPress store with automatic SMS verification and real-time payment tracking.
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <a 
                href="https://github.com/Jefranulislam/payhobe" 
                target="_blank" 
                rel="noopener noreferrer"
                className="btn-primary text-lg px-8 py-3 inline-flex items-center justify-center gap-2"
              >
                Download Plugin
                <HiOutlineArrowRight className="h-5 w-5" />
              </a>
              <Link href="/login" className="btn-secondary text-lg px-8 py-3 text-center">
                Connect Your Store
              </Link>
            </div>
          </div>

          {/* Payment Methods */}
          <div className="mt-16 text-center">
            <p className="text-gray-500 mb-4">Supported Payment Methods</p>
            <div className="flex flex-wrap justify-center gap-4">
              {paymentMethods.map((method) => (
                <div 
                  key={method.name}
                  className={`${method.color} text-white px-6 py-3 rounded-xl font-semibold shadow-lg`}
                >
                  {method.name}
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* Dashboard Preview */}
      <section className="py-20 bg-gray-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">
              Powerful Dashboard
            </h2>
            <p className="text-xl text-gray-600 max-w-2xl mx-auto">
              Monitor your payments, verify transactions, and track revenue in real-time.
            </p>
          </div>
          <div className="bg-gray-900 rounded-2xl shadow-2xl overflow-hidden">
            <div className="flex items-center gap-2 px-4 py-3 bg-gray-800">
              <div className="w-3 h-3 rounded-full bg-red-500"></div>
              <div className="w-3 h-3 rounded-full bg-yellow-500"></div>
              <div className="w-3 h-3 rounded-full bg-green-500"></div>
            </div>
            <div className="p-8 bg-gradient-to-br from-gray-800 to-gray-900">
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                {[
                  { label: 'Total Revenue', value: '৳125,430', color: 'text-green-400' },
                  { label: 'Today', value: '৳8,250', color: 'text-blue-400' },
                  { label: 'Pending', value: '3', color: 'text-yellow-400' },
                  { label: 'Confirmed', value: '156', color: 'text-primary-400' },
                ].map((stat) => (
                  <div key={stat.label} className="bg-gray-700/50 rounded-xl p-4">
                    <p className="text-gray-400 text-sm">{stat.label}</p>
                    <p className={`text-2xl font-bold ${stat.color}`}>{stat.value}</p>
                  </div>
                ))}
              </div>
              <div className="bg-gray-700/30 rounded-xl p-4">
                <div className="flex items-center justify-between mb-4">
                  <span className="text-white font-medium">Recent Payments</span>
                  <span className="text-primary-400 text-sm">View all →</span>
                </div>
                <div className="space-y-3">
                  {[
                    { method: 'bKash', amount: '৳1,250', status: 'Confirmed', time: '2 min ago' },
                    { method: 'Nagad', amount: '৳3,500', status: 'Confirmed', time: '15 min ago' },
                    { method: 'Rocket', amount: '৳850', status: 'Pending', time: '1 hour ago' },
                  ].map((payment, i) => (
                    <div key={i} className="flex items-center justify-between py-2 border-b border-gray-600/50 last:border-0">
                      <div className="flex items-center gap-3">
                        <span className={`px-2 py-1 rounded text-xs font-medium ${
                          payment.method === 'bKash' ? 'bg-pink-500/20 text-pink-400' :
                          payment.method === 'Nagad' ? 'bg-orange-500/20 text-orange-400' :
                          'bg-purple-500/20 text-purple-400'
                        }`}>
                          {payment.method}
                        </span>
                        <span className="text-white">{payment.amount}</span>
                      </div>
                      <div className="flex items-center gap-3">
                        <span className={`text-sm ${payment.status === 'Confirmed' ? 'text-green-400' : 'text-yellow-400'}`}>
                          {payment.status}
                        </span>
                        <span className="text-gray-500 text-sm">{payment.time}</span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section className="py-20 px-4 sm:px-6 lg:px-8">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">
              Everything You Need
            </h2>
            <p className="text-xl text-gray-600 max-w-2xl mx-auto">
              A complete payment solution built specifically for Bangladeshi e-commerce.
            </p>
          </div>
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            {features.map((feature) => (
              <div 
                key={feature.title}
                className="bg-white border border-gray-200 rounded-2xl p-6 hover:shadow-lg transition-shadow"
              >
                <div className="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center mb-4">
                  <feature.icon className="h-6 w-6 text-primary-600" />
                </div>
                <h3 className="text-xl font-semibold text-gray-900 mb-2">{feature.title}</h3>
                <p className="text-gray-600">{feature.description}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* How It Works */}
      <section className="py-20 bg-primary-600">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <h2 className="text-3xl sm:text-4xl font-bold text-white mb-4">
              How It Works
            </h2>
            <p className="text-xl text-primary-100 max-w-2xl mx-auto">
              Get started in minutes with our simple setup process.
            </p>
          </div>
          <div className="grid md:grid-cols-4 gap-8">
            {[
              { step: '1', title: 'Install Plugin', desc: 'Download and install the WordPress plugin' },
              { step: '2', title: 'Configure MFS', desc: 'Add your bKash, Nagad, Rocket accounts' },
              { step: '3', title: 'Setup SMS', desc: 'Install SMS forwarder app on your phone' },
              { step: '4', title: 'Start Selling', desc: 'Accept payments automatically' },
            ].map((item) => (
              <div key={item.step} className="text-center">
                <div className="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold text-primary-600">
                  {item.step}
                </div>
                <h3 className="text-xl font-semibold text-white mb-2">{item.title}</h3>
                <p className="text-primary-100">{item.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Pricing */}
      <section className="py-20 px-4 sm:px-6 lg:px-8">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">
              Simple Pricing
            </h2>
            <p className="text-xl text-gray-600">
              Free and open source. No hidden fees.
            </p>
          </div>
          <div className="max-w-lg mx-auto">
            <div className="bg-white border-2 border-primary-500 rounded-2xl p-8 shadow-xl">
              <div className="text-center mb-6">
                <span className="bg-primary-100 text-primary-700 px-3 py-1 rounded-full text-sm font-medium">
                  Open Source
                </span>
                <div className="mt-4">
                  <span className="text-5xl font-bold text-gray-900">Free</span>
                  <span className="text-gray-500 ml-2">forever</span>
                </div>
              </div>
              <ul className="space-y-4 mb-8">
                {pricingFeatures.map((feature) => (
                  <li key={feature} className="flex items-center gap-3">
                    <HiOutlineCheck className="h-5 w-5 text-green-500 flex-shrink-0" />
                    <span className="text-gray-700">{feature}</span>
                  </li>
                ))}
              </ul>
              <a 
                href="https://github.com/Jefranulislam/payhobe" 
                target="_blank" 
                rel="noopener noreferrer"
                className="block w-full btn-primary text-center py-3"
              >
                Get Started Free
              </a>
            </div>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-20 bg-gray-900">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-3xl sm:text-4xl font-bold text-white mb-4">
            Ready to Accept MFS Payments?
          </h2>
          <p className="text-xl text-gray-400 mb-8">
            Join hundreds of Bangladeshi merchants using PayHobe to power their online stores.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <a 
              href="https://github.com/Jefranulislam/payhobe" 
              target="_blank" 
              rel="noopener noreferrer"
              className="btn-primary text-lg px-8 py-3"
            >
              Download Now
            </a>
            <Link href="/login" className="bg-white text-gray-900 hover:bg-gray-100 px-8 py-3 rounded-lg font-medium transition-colors text-center">
              Connect Store
            </Link>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-gray-900 border-t border-gray-800 py-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col md:flex-row justify-between items-center gap-4">
            <div className="flex items-center gap-2">
              <span className="text-2xl">💳</span>
              <span className="text-xl font-bold text-white">Pay<span className="text-primary-400">Hobe</span></span>
            </div>
            <p className="text-gray-500 text-sm">
              © {new Date().getFullYear()} PayHobe. Open source under MIT License.
            </p>
            <div className="flex items-center gap-6">
              <a 
                href="https://github.com/Jefranulislam/payhobe" 
                target="_blank" 
                rel="noopener noreferrer"
                className="text-gray-400 hover:text-white transition-colors"
              >
                GitHub
              </a>
              <Link href="/login" className="text-gray-400 hover:text-white transition-colors">
                Dashboard
              </Link>
            </div>
          </div>
        </div>
      </footer>
    </div>
  )
}
