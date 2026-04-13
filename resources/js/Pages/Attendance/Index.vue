<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// 1. Layout Persistent
export default { layout: AuthenticatedLayout };
</script>

<script setup>
import Close from '@/Components/Icon/Close.vue';
import Download from '@/Components/Icon/Download.vue';
import Download2 from '@/Components/Icon/Download2.vue';
import Gear from '@/Components/Icon/Gear.vue';
import Clock from '@/Components/Icon/Clock.vue';
import Pagination from '@/Components/Pagination.vue';
import SelectInput from '@/Components/SelectInput.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import moment from 'moment';
import Datepicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

// State Loading Animation
const isLoaded = ref(false);
onMounted(() => {
  setTimeout(() => {
    isLoaded.value = true;
  }, 100);
});

// Page & Props
const page = usePage();
const props = defineProps({
  attendances: Object, 
  users: Array
});

const role = computed(() => page.props.auth.user.role);

// --- STATE FITUR ABSENSI ---
// Mengambil status dari HandleInertiaRequests (props global)
const isAttendanceEnabled = ref(page.props.attendance_enabled);

// Method untuk toggle status absensi
const toggleAttendance = () => {
    router.post(route('attendance.toggle'), {
        is_enabled: isAttendanceEnabled.value
    }, {
        preserveScroll: true
    });
};
// ---------------------------

// Query Params Handling
const queryParams = computed(() => {
  const url = new URL(page.url, window.location.origin);
  return Object.fromEntries(url.searchParams);
});

// Filter States
const options = ref(['other', 'co'].includes(role.value) ? true : false);
const id = ref(Number(queryParams.value.user_id) || null);
const dates = ref(
  queryParams.value.from && queryParams.value.to
    ? [
        moment(queryParams.value.from, 'DD-MM-YYYY').toDate(),
        moment(queryParams.value.to, 'DD-MM-YYYY').toDate(),
      ]
    : []
);

const handleOpenOptions = () => {
  options.value = !options.value;
};

// Grouping Logic
const groupedAttendances = computed(() => {
  const grouped = {};
    if (props.attendances && props.attendances.data) {
        props.attendances.data.forEach(item => {
          const date = moment(item.created_at).format('DD MMMM YYYY');
          if (!grouped[date]) {
            grouped[date] = { items: [], count: 0 };
          }
          grouped[date].items.push(item);
          grouped[date].count += 1;
        });
    }
  return grouped;
});

// Format Helpers
const formatTime = (datetime) => moment(datetime).format('HH:mm:ss');
const formatDate = (date) => moment(date).format('DD MMMM YYYY');

// Watchers for Filters
const updateParams = () => {
  const params = {};
  if (id.value) params.user_id = id.value;
  if (dates.value && dates.value.length === 2) {
    params.from = moment(dates.value[0]).format('DD-MM-YYYY');
    params.to = moment(dates.value[1]).format('DD-MM-YYYY');
  }
  router.get(route('attendance', params), {}, { preserveState: true, replace: true });
};

watch(id, updateParams);

const handleDateChange = (newDate) => {
    dates.value = newDate; 
    updateParams();
};

// Actions
const exportAttendance = (summary) => {
  const params = {};
  if (id.value) params.user_id = id.value;
  if (dates.value && dates.value.length === 2) {
    params.from = moment(dates.value[0]).format('DD-MM-YYYY');
    params.to = moment(dates.value[1]).format('DD-MM-YYYY');
  }
  params.summary = summary;
  window.open(route('attendance.export', params), '_blank');
};

const resetFilter = () => {
    id.value = null;
    dates.value = [];
    router.get(route('attendance'));
}
</script>

<template>
  <Head title="Attendance List" />
  
  <div class="w-full py-8">
    
    <div class="mx-auto max-w-[100rem] sm:px-6 lg:px-0 mb-6" v-if="role === 'other'">
        <div 
            class="p-5 bg-white/40 dark:bg-slate-900/40 backdrop-blur-xl border border-white/40 dark:border-white/20 shadow-lg rounded-lg flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 transition-all duration-1000 ease-out"
            :class="{ 'translate-y-0 opacity-100': isLoaded, 'translate-y-8 opacity-0': !isLoaded }"
        >
            <div>
                <h3 class="font-bold text-gray-800 dark:text-slate-100 drop-shadow-sm">Status Fitur Absensi</h3>
                <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Jika dinonaktifkan, role selain Other tidak akan bisa melihat atau mengakses halaman dan fitur absensi ini.</p>
            </div>
            
            <label class="flex items-center cursor-pointer shrink-0">
                <div class="relative">
                    <input type="checkbox" class="sr-only" v-model="isAttendanceEnabled" @change="toggleAttendance">
                    <div class="block w-14 h-8 rounded-full transition-colors duration-300" :class="isAttendanceEnabled ? 'bg-emerald-500' : 'bg-gray-400 dark:bg-slate-600'"></div>
                    <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition-transform duration-300" :class="{'transform translate-x-6': isAttendanceEnabled}"></div>
                </div>
                <span class="ml-3 font-bold text-sm w-8" :class="isAttendanceEnabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400'">
                    {{ isAttendanceEnabled ? 'ON' : 'OFF' }}
                </span>
            </label>
        </div>
    </div>

    <div v-if="$page.props.attendance_enabled || role === 'other'">
        <div class="mx-auto max-w-[100rem] sm:px-6 lg:px-0">
            <div
            class="flex flex-col md:flex-row justify-between px-6 py-4 items-start md:items-center gap-4 text-gray-800 dark:text-gray-200 
                    bg-white/40 dark:bg-slate-900/40 backdrop-blur-xl border border-white/40 dark:border-white/20 
                    shadow-lg rounded-lg transition-all duration-1000 ease-out"
            :class="{ 'translate-y-0 opacity-100': isLoaded, 'translate-y-8 opacity-0': !isLoaded }"
            >
            <div>
                <h2 class="font-bold text-xl leading-tight text-gray-800 dark:text-slate-100 drop-shadow-sm">
                Employee Attendance
                </h2>
                <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Monitor daily check-in and check-out records.</p>
            </div>
            
            <div class="flex flex-wrap gap-3 justify-end w-full md:w-auto">
                <button
                v-if="['other', 'co'].includes(role)"
                @click="handleOpenOptions"
                class="flex items-center gap-2 px-4 py-2 bg-white/50 dark:bg-slate-800/50 hover:bg-white dark:hover:bg-slate-700/50 text-gray-700 dark:text-gray-200 rounded-lg shadow-sm border border-white/40 dark:border-white/10 backdrop-blur-sm transition-all"
                >
                <Gear class="w-4 h-4" />
                <span class="hidden sm:inline font-medium text-sm">Options</span>
                </button>
            </div>
            </div>
        </div>

        <div
        v-if="options"
        class="w-full pt-4 sm:pt-6 transition-all duration-500 ease-out relative z-30"
        :class="{ 'translate-y-0 opacity-100': isLoaded, 'translate-y-12 opacity-0': !isLoaded }"
        >
        <div class="mx-auto max-w-[100rem] sm:px-6 lg:px-0">
            <div class="relative z-20 bg-white/40 dark:bg-slate-900/40 backdrop-blur-xl p-4 rounded-xl shadow-lg border border-white/40 dark:border-white/10">
            <div class="flex flex-col xl:flex-row gap-4 items-end justify-between w-full">
                
                <div class="flex flex-col sm:flex-row gap-4 w-full xl:w-auto flex-1">
                <div class="w-full sm:w-1/2 xl:w-72">
                    <label class="text-[10px] font-bold text-gray-500 dark:text-slate-400 mb-1 block uppercase tracking-wider">Date Range</label>
                    <Datepicker
                    v-model="dates"
                    range
                    placeholder="Select Range Dates"
                    :enable-time-picker="false"
                    @update:model-value="handleDateChange"
                    input-class-name="glass-datepicker"
                    :clearable="true"
                    />
                </div>
                <div class="w-full sm:w-1/2 xl:w-72">
                    <label class="text-[10px] font-bold text-gray-500 dark:text-slate-400 mb-1 block uppercase tracking-wider">Filter User</label>
                    <SelectInput
                        id="user"
                        v-model="id"
                        :options="users"
                        label="name"
                        valueKey="id"
                        class="block w-full h-[42px]"
                        placeholder="Select user..."
                        :dark="true"
                    />
                </div>
                </div>

                <div class="flex flex-wrap sm:flex-nowrap justify-end gap-3 w-full xl:w-auto">
                <button 
                    @click="exportAttendance(false)" 
                    class="w-full sm:w-auto flex items-center justify-center gap-2 h-[42px] px-4 py-2 text-sm font-bold rounded-lg bg-primary-600 hover:bg-primary-700 text-white transition-all shadow-md"
                >
                    <Download class="w-4 h-4" />
                    <span class="whitespace-nowrap">Export</span>
                </button>
                
                <button 
                    @click="exportAttendance(true)" 
                    class="w-full sm:w-auto flex items-center justify-center gap-2 h-[42px] px-4 py-2 text-sm font-bold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-all shadow-md"
                >
                    <Download2 class="w-4 h-4" />
                    <span class="whitespace-nowrap">Summary</span>
                </button>
                
                <button 
                    @click="resetFilter" 
                    class="w-full sm:w-auto flex items-center justify-center gap-2 h-[42px] px-4 py-2 text-sm font-bold rounded-lg bg-gray-100 dark:bg-slate-700/50 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-600/50 transition-all border border-gray-200 dark:border-slate-600/50 shadow-sm"
                >
                    <Close class="w-4 h-4" />
                    <span>Reset</span>
                </button>
                </div>

            </div>
            </div>
        </div>
        </div>

        <div
        class="w-full py-6 sm:py-8 transition-all duration-700 ease-out delay-100 relative z-10"
        :class="{ 'opacity-100': isLoaded, 'translate-y-12 opacity-0': !isLoaded }"
        >
        <div class="mx-auto max-w-[100rem] sm:px-6 lg:px-0">
            
            <div class="flex flex-col items-start relative">
                
                <div class="relative z-10 -mb-[1px]">
                    <div class="w-fit px-6 h-12 bg-white/40 dark:bg-slate-900/60 backdrop-blur-xl border-t border-l border-r border-white/40 dark:border-white/20 rounded-t-lg shadow-sm relative flex items-center gap-3">
                        <Clock class="w-5 h-5 text-primary-600 dark:text-primary-400 drop-shadow-sm" />
                        <span class="font-bold text-gray-800 dark:text-slate-100 text-sm tracking-wide shadow-black drop-shadow-sm">Attendance Logs</span>
                        <div class="absolute -bottom-[1px] left-0 right-0 h-[2px] bg-white/40 dark:bg-slate-900/80 z-20"></div>
                    </div>
                </div>

                <div class="w-full overflow-x-auto bg-white/40 dark:bg-slate-900/40 backdrop-blur-xl border border-white/40 dark:border-white/20 shadow-xl rounded-b-lg rounded-tr-lg relative z-0 flex flex-col">
                <table class="w-full min-w-[50rem] text-left dark:text-slate-200 table-auto border-collapse">
                    <thead class="bg-white/50 dark:bg-slate-900/80 backdrop-blur-md border-b border-white/20 dark:border-white/10">
                    <tr>
                        <th class="p-5 font-semibold text-gray-600 dark:text-slate-400 text-sm uppercase tracking-wider">Date / Time</th>
                        <th class="p-5 font-semibold text-gray-600 dark:text-slate-400 text-sm uppercase tracking-wider">Employee</th>
                        <th class="p-5 font-semibold text-gray-600 dark:text-slate-400 text-sm uppercase tracking-wider">Type</th>
                        <th class="p-5 font-semibold text-gray-600 dark:text-slate-400 text-sm uppercase tracking-wider text-center">Action Time</th>
                        <th class="p-5 font-semibold text-gray-600 dark:text-slate-400 text-sm uppercase tracking-wider text-center">Proof Photo</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-white/20 dark:divide-white/5">
                    
                        <template v-for="(group, date) in groupedAttendances" :key="date">
                            <tr class="bg-primary-50/50 dark:bg-primary-500/10 backdrop-blur-sm border-t border-white/20 dark:border-white/10">
                                <td class="py-3 px-5" colspan="4">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-primary-100/50 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300 text-xs font-bold border border-primary-200/50 dark:border-primary-500/30">
                                        {{ date }}
                                    </span>
                                </td>
                                <td class="py-3 px-5 text-right">
                                    <span class="text-[10px] font-bold text-gray-500 dark:text-slate-300 uppercase tracking-wide bg-white/50 dark:bg-slate-900/60 border border-gray-200 dark:border-white/5 shadow-sm px-2 py-1 rounded">
                                        Total: {{ group.count }}
                                    </span>
                                </td>
                            </tr>

                            <tr v-for="item in group.items" :key="item.id" class="hover:bg-white/50 dark:hover:bg-white/5 transition duration-200 group">
                                <td class="p-5 text-sm text-gray-500 dark:text-slate-400 font-medium whitespace-nowrap">
                                    {{ formatDate(item.created_at) }}
                                </td>
                                <td class="p-5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-white/60 dark:bg-slate-800/50 flex items-center justify-center text-xs font-bold text-primary-600 dark:text-primary-400 border border-primary-100/50 dark:border-slate-700/50 shadow-sm backdrop-blur-sm">
                                            {{ item.user?.name ? item.user.name.charAt(0).toUpperCase() : 'U' }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-gray-800 dark:text-slate-200 text-sm group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                                {{ item.user?.name || 'Unknown User' }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-slate-400">{{ item.user?.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="p-5">
                                    <span 
                                        class="inline-flex items-center px-2 py-1 rounded text-xs font-bold uppercase shadow-sm border backdrop-blur-sm"
                                        :class="{
                                            'bg-emerald-50/80 text-emerald-700 border-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-400 dark:border-emerald-500/30': item.type === 'check_in',
                                            'bg-rose-50/80 text-rose-700 border-rose-200 dark:bg-rose-500/20 dark:text-rose-400 dark:border-rose-500/30': item.type === 'check_out',
                                            'bg-blue-50/80 text-blue-700 border-blue-200 dark:bg-blue-500/20 dark:text-blue-400 dark:border-blue-500/30': item.type === 'proof_of_delivery'
                                        }"
                                    >
                                        {{ item.type ? item.type.replace('_', ' ') : 'Unknown' }}
                                    </span>
                                </td>

                                <td class="p-5 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-gray-50/80 dark:bg-slate-500/10 text-gray-700 dark:text-slate-300 text-xs font-bold border border-gray-200 dark:border-slate-600 backdrop-blur-sm">
                                        {{ formatTime(item.created_at) }}
                                    </span>
                                </td>

                                <td class="p-5 text-center">
                                    <a v-if="item.photo_path" :href="`/storage/${item.photo_path}`" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-400 dark:hover:bg-indigo-900/50 transition font-medium text-xs border border-indigo-100 dark:border-indigo-800 shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        View Proof
                                    </a>
                                    <span v-else class="text-gray-400 dark:text-gray-500 text-xs italic">No Proof</span>
                                </td>
                            </tr>
                        </template>

                        <tr v-if="Object.keys(groupedAttendances).length === 0">
                            <td colspan="5" class="p-12 text-center text-gray-400 dark:text-gray-500 italic">No attendance records found for this period.</td>
                        </tr>
                    </tbody>
                </table>
                </div>
                
                <div class="mt-6 flex justify-end w-full">
                    <Pagination :links="attendances.links" />
                </div>

            </div>

        </div>
        </div>
    </div>

    <div v-else class="mx-auto max-w-[100rem] sm:px-6 lg:px-0">
        <div 
            class="bg-white/40 dark:bg-slate-900/40 backdrop-blur-xl border border-white/40 dark:border-white/20 shadow-lg rounded-xl p-12 text-center transition-all duration-1000 ease-out"
            :class="{ 'translate-y-0 opacity-100': isLoaded, 'translate-y-8 opacity-0': !isLoaded }"
        >
            <div class="w-20 h-20 mx-auto bg-gray-100 dark:bg-slate-800/50 rounded-full flex items-center justify-center mb-6 border border-gray-200 dark:border-slate-700/50 shadow-sm">
                <Clock class="w-10 h-10 text-gray-400 dark:text-gray-500" />
            </div>
            <h2 class="text-3xl font-bold text-gray-700 dark:text-slate-200 mb-2">Fitur Absensi Dinonaktifkan</h2>
            <p class="text-gray-500 dark:text-slate-400 max-w-md mx-auto">Menu absensi saat ini sedang ditutup oleh Manager. Silakan hubungi atasan Anda untuk informasi lebih lanjut.</p>
        </div>
    </div>

  </div>
</template>

<style scoped>
:deep(.dp__input) {
  background-color: #ffffff;
  backdrop-filter: blur(8px);
  border-color: rgba(255, 255, 255, 0.5);
  border-radius: 0.5rem;
  height: 42px;
  font-size: 0.875rem;
  font-weight: 500;
}

/* Custom Scrollbar Universal */
.custom-scrollbar {
  scrollbar-gutter: stable;
  scrollbar-width: thin;
}
.custom-scrollbar::-webkit-scrollbar {
  height: 6px;
  width: 6px;
  display: block;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent; 
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: rgba(156, 163, 175, 0.5); 
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: rgba(156, 163, 175, 0.8);
}

:global(.dark) .custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: rgba(255, 255, 255, 0.1);
}
:global(.dark) .custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: rgba(255, 255, 255, 0.2);
}
</style>