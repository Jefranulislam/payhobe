'use client'

import { Doughnut, Line } from 'react-chartjs-2'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  Filler,
} from 'chart.js'
import { getMethodColor } from '@/lib/utils'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  Filler
)

interface MethodsChartProps {
  data: Record<string, { count: number; total: number }>
}

export function MethodsChart({ data }: MethodsChartProps) {
  const labels = Object.keys(data).map((m) => m.toUpperCase())
  const values = Object.values(data).map((d) => d.total)
  const colors = Object.keys(data).map((m) => getMethodColor(m))

  const chartData = {
    labels,
    datasets: [
      {
        data: values,
        backgroundColor: colors,
        borderWidth: 0,
      },
    ],
  }

  const options = {
    responsive: true,
    plugins: {
      legend: {
        position: 'bottom' as const,
      },
    },
  }

  return (
    <div className="w-full max-w-xs mx-auto">
      <Doughnut data={chartData} options={options} />
    </div>
  )
}

interface RevenueChartProps {
  data: {
    labels: string[]
    values: number[]
  }
}

export function RevenueChart({ data }: RevenueChartProps) {
  const chartData = {
    labels: data.labels,
    datasets: [
      {
        label: 'Revenue',
        data: data.values,
        fill: true,
        borderColor: 'rgb(59, 130, 246)',
        backgroundColor: 'rgba(59, 130, 246, 0.1)',
        tension: 0.4,
      },
    ],
  }

  const options = {
    responsive: true,
    plugins: {
      legend: {
        display: false,
      },
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          callback: function(value: any) {
            return '৳' + value.toLocaleString()
          },
        },
      },
    },
  }

  return <Line data={chartData} options={options} />
}
