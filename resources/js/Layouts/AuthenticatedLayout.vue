<script setup>
import { ref, watch, computed, onUnmounted } from 'vue'; 
import { Head, router, usePage, useForm } from '@inertiajs/vue3';
import Menus from "@/Components/Menus.vue";
import Hamburger from '@/Components/Icon/Hamburger.vue';
import Logout from '@/Components/Icon/Logout.vue';
import Pen from '@/Components/Icon/Pen.vue';
import Toast from '@/Components/Toast.vue';
import Modal from '@/Components/Modal.vue'; 
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';

// Props
defineProps({
    title: String,
});

// State & Hooks
const page = usePage();
const user = computed(() => page.props.auth.user);

// --- Logic Sidebar Kanan (Profile) ---
const showProfilePanel = ref(false);

// Logic Edit Nama & Avatar
const isEditingName = ref(false);
const nameForm = useForm({ name: '', email: '', avatar: '' });

const startEditingName = () => {
    nameForm.name = user.value.name;
    nameForm.email = user.value.email;
    nameForm.avatar = user.value.avatar || '/avatars/1.png'; // Fallback ke default
    isEditingName.value = true;
};

const saveName = () => {
    nameForm.patch(route('profile.update'), {
        preserveScroll: true, preserveState: true, 
        onSuccess: () => { isEditingName.value = false; }
    });
};

const cancelEditingName = () => {
    isEditingName.value = false;
    nameForm.reset();
};

const showAvatarModal = ref(false);
const avatarForm = useForm({ name: '', email: '', avatar: '' });

const avatarAssets = [
    '/avatars/1.png', '/avatars/2.png', '/avatars/3.png', '/avatars/4.png',
    '/avatars/5.png', '/avatars/6.png', '/avatars/7.png', '/avatars/8.png',
    '/avatars/9.png', '/avatars/10.png', '/avatars/11.png', '/avatars/12.png',
];

const selectedAvatarTemp = ref('');

const openAvatarModal = () => {
    // Jika user.avatar null, tampilkan avatar 1 sebagai pilihan awal di modal
    selectedAvatarTemp.value = user.value.avatar || '/avatars/1.png';
    showAvatarModal.value = true;
};
const selectAvatar = (assetPath) => { selectedAvatarTemp.value = assetPath; };
const saveAvatar = () => {
    avatarForm.name = user.value.name;
    avatarForm.email = user.value.email;
    avatarForm.avatar = selectedAvatarTemp.value;
    avatarForm.patch(route('profile.update'), {
        preserveScroll: true, preserveState: true,
        onSuccess: () => { showAvatarModal.value = false; }
    });
};

// --- Logic Ganti Password ---
const showPasswordModal = ref(false);
const openPasswordModal = () => { showPasswordModal.value = true; };
const closePasswordModal = () => { showPasswordModal.value = false; };

// --- Logic Pilih Skill ---
const showSkillModal = ref(false);
const selectedSkills = ref([]);
const skillForm = useForm({ displayed_skills: [] });

// Dapatkan skill yang saat ini akan ditampilkan (Maksimal 5)
const displayedSkills = computed(() => {
    if (!user.value.skills || user.value.skills.length === 0) return [];
    
    // Jika Anda telah menambahkan kolom `is_displayed` di database:
    const selected = user.value.skills.filter(s => s.is_displayed);
    
    // Jika belum ada yang diset (atau blm ada kolom), ambil 5 teratas
    if (selected.length === 0) {
        return user.value.skills.slice(0, 5);
    }
    return selected.slice(0, 5);
});

const openSkillModal = () => {
    // Isi selectedSkills dengan ID dari skill yang is_displayed = true (atau 5 skill pertama sebagai default awal)
    const currentSelected = user.value.skills.filter(s => s.is_displayed).map(s => s.id);
    if (currentSelected.length === 0) {
        selectedSkills.value = user.value.skills.slice(0, 5).map(s => s.id);
    } else {
        selectedSkills.value = currentSelected;
    }
    showSkillModal.value = true;
};

const saveSkills = () => {
    skillForm.displayed_skills = selectedSkills.value;
    // Sesuaikan nama route dengan backend Anda untuk menyimpan status skill ini
    skillForm.patch(route('profile.skills.update'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { showSkillModal.value = false; }
    });
};

// --- Logic Sidebar Kiri (Navigation) ---
const getInitialSidebarState = () => {
    if (typeof window !== 'undefined') {
        const savedState = localStorage.getItem('sidebarOpen');
        if (savedState !== null) return savedState === 'true';
        return window.innerWidth >= 768;
    }
    return true;
};

const openMenus = ref(getInitialSidebarState());
watch(openMenus, (newValue) => { localStorage.setItem('sidebarOpen', newValue); });

// Mutual Exclusivity & Body Scroll Lock
watch(openMenus, (isOpen) => { if (isOpen) showProfilePanel.value = false; });

watch(showProfilePanel, (isOpen) => {
    if (isOpen) {
        openMenus.value = false;
        document.body.style.overflow = 'hidden';
    } else {
        if (window.innerWidth >= 1024) openMenus.value = true;
        document.body.style.overflow = '';
    }
});

onUnmounted(() => {
    document.body.style.overflow = '';
});

const logout = () => { router.post(route('logout')); };
const getInitials = (name) => {
    if (!name) return 'U';
    const names = name.split(' ');
    let initials = names[0].substring(0, 1).toUpperCase();
    if (names.length > 1) initials += names[names.length - 1].substring(0, 1).toUpperCase();
    return initials;
};
</script>

<template>
    <div class="min-h-screen bg-gradient-to-b from-[#F2F5FA] to-[#e0e7ff] dark:bg-[url('/background/dark_theme.jpg')] dark:bg-cover dark:bg-center dark:bg-fixed dark:bg-no-repeat font-sans text-slate-600 dark:text-slate-300 relative selection:bg-primary-500 selection:text-white transition-colors duration-500">
        <Head :title="title" />

        <nav class="md:hidden fixed top-0 left-0 w-full bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-white/10 z-50 shadow-sm">
            <div class="flex justify-between items-center px-4 h-16">
                <div class="flex items-center gap-3">
                    <button @click="openMenus = !openMenus" class="p-2 text-gray-600 dark:text-gray-300 rounded-none hover:bg-white/20 focus:outline-none transition">
                        <Hamburger :show="openMenus" />
                    </button>
                    <img src="/icon_kndi.svg" alt="Logo" class="w-8 h-8 drop-shadow-md" />
                    <span class="font-bold text-lg text-gray-800 dark:text-white tracking-tight">Drivio Magament</span>
                </div>
                <button @click="showProfilePanel = true" class="relative">
                     <div v-if="user.avatar" class="w-8 h-8 rounded-none overflow-hidden border border-white/50 shadow-sm bg-gray-50 dark:bg-slate-800">
                        <img :src="user.avatar" alt="Avatar" class="w-full h-full object-contain">
                    </div>
                    <div v-else class="w-8 h-8 rounded-none bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center text-primary-600 dark:text-primary-300 font-bold text-sm shadow-inner">
                         {{ getInitials(user.name) }}
                    </div>
                </button>
            </div>

            <div v-if="openMenus" class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl border-t border-white/20 dark:border-white/10 shadow-xl overflow-y-auto max-h-[80vh]">
                <div class="flex flex-col p-4 space-y-4">
                    <Menus :sidebarOpen="true" />
                </div>
            </div>
        </nav>

        <nav 
            class="hidden md:flex fixed top-4 left-4 bottom-4 z-50 transition-all duration-500 shadow-2xl flex-col rounded-none border border-white/20 dark:border-white/10" 
            :class="[
                openMenus ? 'w-72' : 'w-24',
                showProfilePanel ? '-translate-x-[200%]' : 'translate-x-0',
                'bg-[#0d1b3e] dark:bg-slate-900/90 backdrop-blur-xl'
            ]"
        >
            <div 
                class="h-24 flex items-center shrink-0 text-white transition-all duration-300 relative z-20"
                :class="openMenus ? 'justify-between pl-8 pr-6' : 'justify-center px-0'" 
            >
                <div v-if="openMenus" class="flex items-center gap-3 overflow-hidden whitespace-nowrap animate-fade-in">
                    <div class="bg-white/10 p-1.5 rounded-none shadow-lg border border-white/10">
                        <img src="/icon_kndi.svg" alt="Logo" class="w-6 h-6 shrink-0" />
                    </div>
                    <span class="text-white font-bold text-2xl tracking-wide">Drivio</span>
                </div>
                
                <button 
                    @click="openMenus = !openMenus" 
                    class="text-gray-300 hover:text-white transition focus:outline-none p-2 rounded-none"
                >
                    <Hamburger :show="openMenus" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto overflow-x-visible py-6 no-scrollbar relative z-10">
                <Menus :sidebarOpen="openMenus" />
            </div>
        </nav>

        <div 
            class="flex-1 flex flex-col min-h-screen transition-all duration-500 ease-out pt-16 md:pt-0" 
            :class="{
                'md:pl-[20rem] md:pr-4': openMenus && !showProfilePanel, 
                'md:pl-[8rem] md:pr-4': !openMenus && !showProfilePanel,
                'md:pl-6 md:pr-[22rem]': showProfilePanel
            }"
        >
            <header class="hidden md:flex h-24 items-center justify-between px-4 sticky top-0 z-40 transition-colors duration-300
                bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-white/5 shadow-sm">
                <div>
                    <h1 class="text-3xl font-bold text-[#0d1b3e] dark:text-slate-100 tracking-tight">
                        Hello, {{ user.name.split(' ')[0] }}!
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 font-medium mt-1">Have a productive day.</p>
                </div>

                <div class="flex items-center gap-4">
                    <button 
                        @click="showProfilePanel = !showProfilePanel"
                        class="flex items-center gap-3 p-1.5 pr-5 rounded-none bg-white dark:bg-slate-800 shadow-sm hover:shadow-lg transition-all duration-300 group border border-gray-200 dark:border-white/10"
                    >
                        <div v-if="user.avatar" class="w-10 h-10 rounded-none overflow-hidden border border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-900">
                             <img :src="user.avatar" alt="User Avatar" class="w-full h-full object-contain">
                        </div>
                        <div v-else class="w-10 h-10 rounded-none bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center text-primary-600 dark:text-primary-300 font-bold text-sm">
                            {{ getInitials(user.name) }}
                        </div>

                        <div class="text-left hidden lg:block">
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200 group-hover:text-primary-600 transition">{{ user.name }}</p>
                        </div>
                    </button>
                </div>
            </header>

            <main class="flex-1 px-4 pb-8">
                <slot />
            </main>
        </div>

        <aside 
            class="fixed top-0 right-0 h-screen w-80 shadow-2xl z-[70] transform transition-transform duration-500 overflow-y-auto border-l border-white/20 dark:border-white/10
            bg-white dark:bg-slate-900 rounded-none"
            :class="showProfilePanel ? 'translate-x-0' : 'translate-x-full'"
        >
            <div class="sticky top-0 right-0 flex justify-end p-4 z-[80] bg-white dark:bg-slate-900">
                <button 
                    @click="showProfilePanel = false" 
                    class="flex items-center justify-center w-10 h-10 text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-none transition-all duration-200"
                    title="Close profile"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="px-6 pb-6 flex flex-col h-full relative">
                <h2 class="text-lg font-bold text-[#0d1b3e] dark:text-white mb-8 mt-2 uppercase tracking-widest opacity-50">User Profile</h2>

                <div class="flex flex-col items-center mb-8 w-full">
                    <div class="relative group cursor-pointer mb-6" @click="openAvatarModal">
                        <div class="w-24 h-24 rounded-none bg-primary-50 dark:bg-slate-800 border-4 border-gray-100 dark:border-slate-700 shadow-xl flex items-center justify-center text-3xl font-bold text-primary-600 relative overflow-hidden">
                            <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-contain" alt="Profile">
                            <span v-else>{{ getInitials(user.name) }}</span>
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <span class="text-white font-bold text-xs">CHANGE</span>
                            </div>
                        </div>
                    </div>

                    <div class="w-full text-center">
                        <div v-if="!isEditingName" class="flex items-center justify-center gap-2 group">
                            <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ user.name }}</h2>
                            <button @click="startEditingName" class="p-1 text-slate-400 group-hover:text-primary-500 transition">
                                <Pen class="w-3.5 h-3.5" />
                            </button>
                        </div>
                        <div v-else class="flex items-center justify-center gap-2 animate-fade-in">
                            <TextInput 
                                v-model="nameForm.name" 
                                class="py-1 px-2 text-center text-sm font-bold w-40 bg-gray-50 dark:bg-slate-800 border-gray-200 rounded-none" 
                                :disabled="nameForm.processing"
                                @keyup.enter="saveName" 
                            />
                            <button 
                                @click="saveName" 
                                :disabled="nameForm.processing"
                                class="p-1.5 bg-primary-600 text-white rounded-none hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center min-w-[28px]"
                            >
                                <svg v-if="nameForm.processing" class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span v-else>✓</span>
                            </button>
                            <button 
                                @click="cancelEditingName" 
                                :disabled="nameForm.processing"
                                class="p-1.5 bg-gray-200 text-gray-600 rounded-none hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed"
                            >✕</button>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">{{ user.email }}</p>
                    </div>
                </div>

                <div class="flex-1">
                    <div class="bg-gray-50 dark:bg-slate-800/50 rounded-none p-5 mb-6 border border-gray-100 dark:border-white/5">
                        <div class="flex justify-between items-center mb-3 group">
                            <h3 class="font-bold text-xs uppercase tracking-widest text-slate-400">Skills</h3>
                            <button @click="openSkillModal" class="p-1 text-slate-400 hover:text-primary-500 transition" title="Pilih Skill untuk Ditampilkan">
                                <Pen class="w-3.5 h-3.5" />
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                             <template v-if="displayedSkills && displayedSkills.length">
                                <span v-for="(skillItem, index) in displayedSkills" :key="index" class="px-2.5 py-1 rounded-none text-[10px] font-bold bg-white dark:bg-primary-500/20 text-primary-600 dark:text-primary-300 border border-primary-100 dark:border-primary-500/30">
                                    {{ skillItem.skill }}
                                </span>
                            </template>
                            <span v-else class="text-xs text-slate-400 italic">No skills selected.</span>
                        </div>
                    </div>

                    <div class="mt-auto pt-6 border-t border-gray-100 dark:border-white/10 space-y-2">
                        <button 
                            @click="openPasswordModal"
                            class="w-full flex items-center justify-center gap-2 px-4 py-3 text-sm font-bold text-slate-600 dark:text-slate-300 bg-gray-50 dark:bg-slate-800/50 hover:bg-primary-50 dark:hover:bg-primary-500/10 hover:text-primary-600 rounded-none transition-all border border-transparent hover:border-primary-100"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <span>Change Password</span>
                        </button>

                        <button 
                            @click="logout" 
                            class="w-full flex items-center justify-center gap-2 px-4 py-3 text-sm font-bold text-red-500 bg-red-50 dark:bg-red-500/10 hover:bg-red-100 hover:text-red-600 rounded-none transition-all border border-transparent hover:border-red-100"
                        >
                            <Logout class="w-4 h-4" />
                            <span>Sign Out</span>
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <Modal :show="showAvatarModal" @close="showAvatarModal = false" max-width="2xl">
            <div class="p-8 bg-white dark:bg-slate-800 rounded-lg"> 
                <h2 class="text-xl font-bold text-gray-900 dark:text-slate-100 mb-8 text-center md:text-left">Select Profile Avatar</h2>
                
                <div class="flex flex-col md:flex-row gap-10">
                    <div class="flex flex-col items-center md:items-start space-y-4">
                        <span class="text-xs font-bold uppercase tracking-widest text-slate-400">Preview</span>
                        <div class="w-48 h-48 bg-slate-50 dark:bg-slate-800 border-2 border-dashed border-slate-200 dark:border-slate-700 flex items-center justify-center overflow-hidden rounded-xl shadow-inner">
                            <img 
                                :src="selectedAvatarTemp" 
                                alt="Avatar Preview" 
                                class="w-full h-full object-contain p-2" 
                            />
                        </div>
                    </div>

                    <div class="flex-1">
                        <span class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-4 block">Available Assets</span>
                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-4 max-h-[320px] overflow-y-auto pr-2 no-scrollbar p-1"> 
                            <button 
                                v-for="(asset, index) in avatarAssets" 
                                :key="index" 
                                @click="selectAvatar(asset)" 
                                class="relative aspect-square flex items-center justify-center transition-all duration-300 rounded-xl group"
                                :class="[
                                    selectedAvatarTemp === asset 
                                        ? 'bg-white dark:bg-slate-700 border-2 border-primary-500 shadow-lg scale-95 ring-2 ring-primary-500/20' 
                                        : 'bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700/60 shadow-sm hover:shadow-md hover:border-primary-400 dark:hover:border-primary-500 hover:-translate-y-1'
                                ]"
                            >
                                <img :src="asset" class="w-full h-full object-contain p-2" />
                                
                                <div v-if="selectedAvatarTemp === asset" class="absolute -top-2 -right-2 bg-primary-500 text-white rounded-full p-1 shadow-lg border-2 border-white dark:border-slate-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3">
                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-10 pt-6 border-t border-gray-100 dark:border-white/10">
                    <SecondaryButton @click="showAvatarModal = false" class="rounded-lg px-6" :disabled="avatarForm.processing">Cancel</SecondaryButton>
                    <PrimaryButton 
                        @click="saveAvatar" 
                        :disabled="avatarForm.processing" 
                        class="rounded-lg px-8 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg v-if="avatarForm.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ avatarForm.processing ? 'Updating...' : 'Update Avatar' }}</span>
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showSkillModal" @close="showSkillModal = false" max-width="md">
            <div class="p-6 bg-white dark:bg-slate-900 rounded-lg">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-slate-100">Display Skills (Max 5)</h2>
                    <button @click="showSkillModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="space-y-1 max-h-60 overflow-y-auto pr-2 no-scrollbar mb-4">
                    <div v-if="user.skills.length === 0" class="text-sm text-slate-500 italic py-4">
                        You haven't added any skills yet.
                    </div>
                    <label 
                        v-for="skill in user.skills" 
                        :key="skill.id" 
                        class="flex items-center space-x-3 cursor-pointer p-3 border border-transparent hover:bg-gray-50 dark:hover:bg-slate-800 rounded-lg transition-colors"
                        :class="{'opacity-50 cursor-not-allowed': selectedSkills.length >= 5 && !selectedSkills.includes(skill.id)}"
                    >
                        <input 
                            type="checkbox" 
                            :value="skill.id" 
                            v-model="selectedSkills"
                            :disabled="selectedSkills.length >= 5 && !selectedSkills.includes(skill.id)"
                            class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:bg-slate-700 dark:border-slate-600"
                        >
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ skill.skill }}</span>
                    </label>
                </div>
                
                <div class="text-xs text-slate-500 mb-6">
                    Selected: {{ selectedSkills.length }} / 5
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-white/10">
                    <SecondaryButton @click="showSkillModal = false" :disabled="skillForm.processing">Cancel</SecondaryButton>
                    <PrimaryButton 
                        @click="saveSkills" 
                        :disabled="skillForm.processing"
                        class="flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg v-if="skillForm.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ skillForm.processing ? 'Saving...' : 'Save Changes' }}</span>
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showPasswordModal" @close="closePasswordModal">
            <div class="p-6 bg-white dark:bg-slate-900 rounded-lg">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-slate-100">Change Password</h2>
                    <button @click="closePasswordModal" class="text-gray-400 hover:text-gray-600"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button>
                </div>
                <UpdatePasswordForm @close="closePasswordModal" />
            </div>
        </Modal>

        <Toast />
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>