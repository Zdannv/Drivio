<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';

defineProps({
    users: Object,
});

const showModal = ref(false);
const isEditing = ref(false);
const currentUserId = ref(null);

const form = useForm({
    _method: 'post', // Inheriting Inertia.js method-spoofing defaults
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    face_image: null,
});

const openCreateModal = () => {
    isEditing.value = false;
    currentUserId.value = null;
    form.reset();
    form.clearErrors();
    form._method = 'post';
    showModal.value = true;
};

const openEditModal = (user) => {
    isEditing.value = true;
    currentUserId.value = user.id;
    form.reset();
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form._method = 'put'; // Overriding for file uploads mapped to Update Method Injection
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    if (isEditing.value) {
        form.post(route('user.update', currentUserId.value), {
            onSuccess: () => {
                closeModal();
                alert('Driver updated successfully!');
            },
        });
    } else {
        form.post(route('user.store'), {
            onSuccess: () => {
                closeModal();
                alert('Driver registered successfully!');
            },
        });
    }
};

const confirmDelete = (user) => {
    if (confirm(`Are you sure you want to completely delete driver ${user.name}? This action unassigns tasks and wipes AI data.`)) {
        router.delete(route('user.destroy', user.id), {
            onSuccess: () => alert('Driver removed from active platform constraints.')
        });
    }
};
</script>

<template>
    <Head title="Manage Drivers" />

    <AuthenticatedLayout>
        <div class="py-10 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            <div class="sm:flex sm:items-center">
                <div class="sm:flex-auto">
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Registered Drivers</h1>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-400">A list of all logistics drivers and associated AI-Reference datasets.</p>
                </div>
                <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                    <button
                        @click="openCreateModal"
                        type="button"
                        class="block rounded-md bg-emerald-600 px-4 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 transition"
                    >
                        Register New Driver
                    </button>
                </div>
            </div>

            <!-- Table UI -->
            <div class="mt-8 flow-root">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-300 dark:divide-slate-700 bg-white dark:bg-slate-800">
                                <thead class="bg-gray-50 dark:bg-slate-900">
                                    <tr>
                                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-6 w-16">No.</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Name</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Email</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Role</th>
                                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900 dark:text-white">Face Photo</th>
                                        <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900 dark:text-white">Management Options</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                                    <tr v-for="(user, index) in users.data" :key="user.id">
                                        <!-- Numbering Column -->
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 dark:text-gray-400 sm:pl-6">
                                            {{ users.from + index }}
                                        </td>
                                        
                                        <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ user.name }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-emerald-600 dark:text-emerald-400 uppercase font-bold tracking-wider">{{ user.role }}</td>
                                        
                                        <!-- View Face Column -->
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-center">
                                            <a v-if="user.face_image_path" :href="`/storage/${user.face_image_path}`" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-400 dark:hover:bg-indigo-900/50 transition font-medium text-xs border border-indigo-100 dark:border-indigo-800">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                View
                                            </a>
                                            <span v-else class="text-gray-400 dark:text-gray-600 text-xs italic">No Photo</span>
                                        </td>

                                        <!-- Actions Column -->
                                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                            <button @click="openEditModal(user)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 mr-4 font-semibold transition">Edit Profile</button>
                                            <button @click="confirmDelete(user)" class="text-rose-600 dark:text-rose-400 hover:text-rose-900 dark:hover:text-rose-300 font-semibold transition">Erase</button>
                                        </td>
                                    </tr>
                                    <tr v-if="!users.data || users.data.length === 0">
                                        <td colspan="5" class="text-center py-8 text-gray-500 dark:text-gray-400">Database constraints returned clear. No logistics active.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination Container -->
                        <div class="mt-6 mb-2 flex flex-col md:flex-row justify-between items-center text-sm text-gray-600 dark:text-gray-400">
                            <div class="mb-4 md:mb-0">
                                Showing {{ users.from || 0 }} to {{ users.to || 0 }} of {{ users.total || 0 }} entries
                            </div>
                            <Pagination v-if="users.links" :links="users.links" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Integrated Modal Overlay -->
            <Teleport to="body">
                <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center">
                    
                    <div class="absolute inset-0 bg-gray-900/70 backdrop-blur-sm" @click="closeModal"></div>

                    <div class="relative z-10 w-full max-w-lg mx-4 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 overflow-hidden">
                        <form @submit.prevent="submit" class="p-6">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ isEditing ? 'Edit Existing Pilot Data' : 'Register New Hardware Driver' }}</h3>
                                <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Entity Name</label>
                                    <input v-model="form.name" type="text" class="block w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition">
                                    <p v-if="form.errors.name" class="text-rose-500 text-xs mt-1">{{ form.errors.name }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Communications Network (Email)</label>
                                    <input v-model="form.email" type="email" class="block w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition">
                                    <p v-if="form.errors.email" class="text-rose-500 text-xs mt-1">{{ form.errors.email }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Security Hexcode (Password)
                                        <span v-if="isEditing" class="text-gray-400 font-normal text-xs ml-1">(Bypass blank to sustain current)</span>
                                    </label>
                                    <input v-model="form.password" type="password" class="block w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition">
                                    <p v-if="form.errors.password" class="text-rose-500 text-xs mt-1">{{ form.errors.password }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm Security Hexcode</label>
                                    <input v-model="form.password_confirmation" type="password" class="block w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Facial Structural Vector (.jpg, .png)
                                        <span v-if="isEditing" class="text-gray-400 font-normal text-xs ml-1">(Optional AI reference overwrite)</span>
                                    </label>
                                    <input
                                        @change="e => form.face_image = e.target.files[0]"
                                        type="file"
                                        accept="image/jpeg,image/png,image/jpg"
                                        class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-900/30 dark:file:text-emerald-400 cursor-pointer"
                                    >
                                    <p v-if="form.errors.face_image" class="text-rose-500 text-xs mt-1">{{ form.errors.face_image }}</p>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end gap-3">
                                <button type="button" @click="closeModal" class="rounded-lg border border-gray-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition">
                                    Discard
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="rounded-lg gap-2 flex items-center bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50 transition shadow-md"
                                >
                                    <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span>{{ form.processing ? 'Syncing...' : (isEditing ? 'Commit Profile Patch' : 'Push New Driver') }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>
        </div>
    </AuthenticatedLayout>
</template>