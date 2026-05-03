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

// Mock data (in real app, this would come from API)
const chartData = ref({
  dates: [],
  newUsers: [],
  totalUsers: []
})

onMounted(async () => {
  await nextTick()
  await loadData()
  initChart()
  
  // Handle window resize
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
  await new Promise(resolve => setTimeout(resolve, 1000))
  
  // Generate mock data for the last 30 days
  const dates = []
  const newUsers = []
  const totalUsers = []
  
  let total = 1000 // Starting total
  
  for (let i = 29; i >= 0; i--) {
    const date = new Date()
    date.setDate(date.getDate() - i)
    dates.push(date.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' }))
    
    // Random new users between 5-50
    const newUsersCount = Math.floor(Math.random() * 45) + 5
    newUsers.push(newUsersCount)
    
    total += newUsersCount
    totalUsers.push(total)
  }
  
  chartData.value = { dates, newUsers, totalUsers }
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
        label: {
          backgroundColor: '#6a7985'
        }
      },
      formatter: function(params) {
        let result = `<div class="font-medium">${params[0].axisValue}</div>`
        params.forEach(param => {
          const value = param.seriesName === 'Total Utilisateurs' 
            ? param.value.toLocaleString('fr-FR')
            : param.value
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
      data: ['Nouveaux Utilisateurs', 'Total Utilisateurs'],
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
        boundaryGap: false,
        data: chartData.value.dates,
        axisLabel: {
          fontSize: 11,
          color: '#6B7280'
        }
      }
    ],
    yAxis: [
      {
        type: 'value',
        name: 'Nouveaux',
        position: 'left',
        axisLabel: {
          fontSize: 11,
          color: '#6B7280'
        },
        splitLine: {
          lineStyle: {
            color: '#F3F4F6'
          }
        }
      },
      {
        type: 'value',
        name: 'Total',
        position: 'right',
        axisLabel: {
          fontSize: 11,
          color: '#6B7280',
          formatter: function(value) {
            return value >= 1000 ? (value / 1000).toFixed(1) + 'K' : value
          }
        }
      }
    ],
    series: [
      {
        name: 'Nouveaux Utilisateurs',
        type: 'bar',
        yAxisIndex: 0,
        data: chartData.value.newUsers,
        itemStyle: {
          color: '#3B82F6'
        },
        emphasis: {
          itemStyle: {
            color: '#2563EB'
          }
        }
      },
      {
        name: 'Total Utilisateurs',
        type: 'line',
        yAxisIndex: 1,
        data: chartData.value.totalUsers,
        smooth: true,
        lineStyle: {
          color: '#10B981',
          width: 3
        },
        itemStyle: {
          color: '#10B981'
        },
        areaStyle: {
          color: {
            type: 'linear',
            x: 0,
            y: 0,
            x2: 0,
            y2: 1,
            colorStops: [
              { offset: 0, color: 'rgba(16, 185, 129, 0.3)' },
              { offset: 1, color: 'rgba(16, 185, 129, 0.05)' }
            ]
          }
        }
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
        xAxis: [{ data: newData.dates }],
        series: [
          { data: newData.newUsers },
          { data: newData.totalUsers }
        ]
      })
    }
  }
})
</script>