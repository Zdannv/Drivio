<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    deliveries: Array,
    drivers: Array,
});

const showModal = ref(false);

const form = useForm({
    driver_id: '',
    destination_address: '',
    destination_lat: '',
    destination_lng: '',
});

const submit = () => {
    form.post('/deliveries', {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
            alert('Delivery created successfully.');
        },
        onError: () => {
            alert('Error dispatching delivery. Refer to the form hints highlighted in red.');
        }
    });
};
</script>

<template>
  <Head title="Manage Deliveries" />

  <div class="py-10 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Active Deliveries</h1>
        <p class="mt-2 text-sm text-gray-700 dark:text-gray-400">A complete list of all logistical deliveries across the system.</p>
      </div>
      <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
        <button @click="showModal = true" type="button" class="block rounded-md bg-emerald-600 px-4 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">
          Create Delivery Task
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="mt-8 flow-root">
      <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300 dark:divide-slate-700 bg-white dark:bg-slate-800">
              <thead class="bg-gray-50 dark:bg-slate-900">
                <tr>
                  <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-6">ID</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Assigned Driver</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Destination</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Coordinates</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                <tr v-for="delivery in deliveries" :key="delivery.id">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white sm:pl-6">#{{ delivery.id }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-indigo-600 dark:text-indigo-400">{{ delivery.driver?.name || 'Unassigned' }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">{{ delivery.destination_address }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400 font-mono">{{ delivery.destination_lat }}, {{ delivery.destination_lng }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm uppercase">
                    <span class="px-2 py-1 text-xs font-bold rounded-full" 
                          :class="{
                             'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': delivery.status === 'pending',
                             'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': delivery.status === 'on_way',
                             'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400': delivery.status === 'completed'
                          }">
                       {{ delivery.status.replace('_', ' ') }}
                    </span>
                  </td>
                </tr>
                <tr v-if="deliveries.length === 0">
                    <td colspan="5" class="text-center py-8 text-gray-500">No deliveries created yet.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="relative z-50 animate-fade-in-up" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm transition-opacity" @click.self="showModal = false"></div>
      <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-700">
            <form @submit.prevent="submit" class="p-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Create Delivery Task</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Assign Driver</label>
                        <select v-model="form.driver_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm">
                            <option value="" disabled>Select a driver...</option>
                            <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                                {{ driver.name }} ({{ driver.email }})
                            </option>
                        </select>
                        <div v-if="form.errors.driver_id" class="text-red-500 text-xs mt-1">{{ form.errors.driver_id }}</div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Destination Address</label>
                        <input v-model="form.destination_address" type="text" placeholder="e.g. 123 Jalan Raya Logistics" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm">
                        <div v-if="form.errors.destination_address" class="text-red-500 text-xs mt-1">{{ form.errors.destination_address }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Latitude</label>
                            <input v-model="form.destination_lat" type="number" step="any" placeholder="-7.2504" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm">
                            <div v-if="form.errors.destination_lat" class="text-red-500 text-xs mt-1">{{ form.errors.destination_lat }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Longitude</label>
                            <input v-model="form.destination_lng" type="number" step="any" placeholder="112.7688" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm">
                            <div v-if="form.errors.destination_lng" class="text-red-500 text-xs mt-1">{{ form.errors.destination_lng }}</div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" @click="showModal = false" class="rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-slate-700">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="inline-flex justify-center rounded-md border border-transparent bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50">Dispatch Delivery</button>
                </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
export default { layout: AuthenticatedLayout };
</script>

<style scoped>
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up {
  animation: fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
