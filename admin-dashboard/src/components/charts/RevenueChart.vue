<template>
  <div class="relative">
    <div
      ref="chartContainer"
      class="h-64 w-full"
    ></div>

    <!-- Loading overlay -->
    <div
      v-if="isLoading"
      class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-75"
    >
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import * as echarts from 'echarts'

const chartContainer = ref(null)
const chart = ref(null)
const isLoading = ref(true)

// Mock data
const chartData = ref({
  months: [],
  revenue: [],
  subscriptions: []
})

onMounted(async () => {
  await nextTick()
  await loadData()
  initChart()

  window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
  if (chart.value) {
    chart.value.dispose()
  }
  window.removeEventListener('resize', handleResize)
})

async function loadData() {
  // Simulate API call
  await new Promise(resolve => setTimeout(resolve, 800))

  // Generate mock data for the last 12 months
  const months = []
  const revenue = []
  const subscriptions = []

  const monthNames = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc']

  for (let i = 11; i >= 0; i--) {
    const date = new Date()
    date.setMonth(date.getMonth() - i)
    months.push(monthNames[date.getMonth()])

    // Generate realistic revenue growth
    const baseRevenue = 15000 + (11 - i) * 2000
    const variation = Math.random() * 5000 - 2500
    revenue.push(Math.max(10000, baseRevenue + variation))

    // Generate subscription count
    const baseSubscriptions = 50 + (11 - i) * 8
    const subVariation = Math.random() * 20 - 10
    subscriptions.push(Math.max(30, Math.round(baseSubscriptions + subVariation)))
  }

  chartData.value = { months, revenue, subscriptions }
  isLoading.value = false
}

function initChart() {
  if (!chartContainer.value) return

  chart.value = echarts.init(chartContainer.value)

  const option = {
    tooltip: {
      trigger: 'axis',
      axisPointer: {
        type: 'cross',
        crossStyle: {
          color: '#999'
        }
      },
      formatter: function(params) {
        let result = `<div class="font-medium">${params[0].axisValue}</div>`
        params.forEach(param => {
          let value = param.value
          if (param.seriesName === 'Revenus') {
            value = new Intl.NumberFormat('fr-FR', {
              style: 'currency',
              currency: 'EUR'
            }).format(value)
          }
          result += `
            <div class="flex items-center mt-1">
              <div class="w-3 h-3 rounded-full mr-2" style="background-color: ${param.color}"></div>
              <span class="text-sm">${param.seriesName}: <strong>${value}</strong></span>
            </div>
          `
        })
        return result
      }
    },
    legend: {
      data: ['Revenus', 'Abonnements'],
      bottom: 0
    },
    grid: {
      left: '3%',
      right: '4%',
      bottom: '15%',
      containLabel: true
    },
    xAxis: [
      {
        type: 'category',
        data: chartData.value.months,
        axisPointer: {
          type: 'shadow'
        },
        axisLabel: {
          fontSize: 11,
          color: '#6B7280'
        }
      }
    ],
    yAxis: [
      {
        type: 'value',
        name: 'Revenus (€)',
        position: 'left',
        axisLabel: {
          fontSize: 11,
          color: '#6B7280',
          formatter: function(value) {
            return value >= 1000 ? (value / 1000).toFixed(0) + 'K€' : value + '€'
          }
        },
        splitLine: {
          lineStyle: {
            color: '#F3F4F6'
          }
        }
      },
      {
        type: 'value',
        name: 'Abonnements',
        position: 'right',
        axisLabel: {
          fontSize: 11,
          color: '#6B7280'
        }
      }
    ],
    series: [
      {
        name: 'Revenus',
        type: 'bar',
        yAxisIndex: 0,
        data: chartData.value.revenue,
        itemStyle: {
          color: {
            type: 'linear',
            x: 0,
            y: 0,
            x2: 0,
            y2: 1,
            colorStops: [
              { offset: 0, color: '#8B5CF6' },
              { offset: 1, color: '#A78BFA' }
            ]
          }
        },
        emphasis: {
          itemStyle: {
            color: {
              type: 'linear',
              x: 0,
              y: 0,
              x2: 0,
              y2: 1,
              colorStops: [
                { offset: 0, color: '#7C3AED' },
                { offset: 1, color: '#8B5CF6' }
              ]
            }
          }
        }
      },
      {
        name: 'Abonnements',
        type: 'line',
        yAxisIndex: 1,
        data: chartData.value.subscriptions,
        smooth: true,
        lineStyle: {
          color: '#F59E0B',
          width: 3
        },
        itemStyle: {
          color: '#F59E0B'
        },
        symbol: 'circle',
        symbolSize: 6
      }
    ]
  }

  chart.value.setOption(option)
}

function handleResize() {
  if (chart.value) {
    chart.value.resize()
  }
}

// Expose method to update data
defineExpose({
  updateData: (newData) => {
    chartData.value = newData
    if (chart.value) {
      chart.value.setOption({
        xAxis: [{ data: newData.months }],
        series: [
          { data: newData.revenue },
          { data: newData.subscriptions }
        ]
      })
    }
  }
})
</script>