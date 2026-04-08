<script setup>
import { computed, ref, watch } from "vue";
import { Link, usePage } from "@inertiajs/vue3";

import User from "@/Components/Icon/User.vue";
import Build from "@/Components/Icon/Build.vue";
import Folder from "@/Components/Icon/Folder.vue";
import Document from "@/Components/Icon/Document.vue";
import News from "@/Components/Icon/News.vue";
import Clock from "@/Components/Icon/Clock.vue";
import Book from "@/Components/Icon/Book.vue";
import Warning from "@/Components/Icon/Warning.vue";
import Cloud from "@/Components/Icon/Cloud.vue";
import UserPlus from "@/Components/Icon/UserPlus.vue";

const props = defineProps({
    sidebarOpen: Boolean,
});

const page = usePage();
const userRole = computed(() => page.props.auth.user.role);

const rawMenuItems = computed(() => [
    { label: "Live Map Dashboard", route: "dashboard", icon: News, show: true, pattern: "dashboard" },
    { label: "Manage Drivers", route: "user.list", icon: User, show: userRole.value === 'admin', pattern: "user.list" },
    { label: "Manage Deliveries", route: "admin.deliveries.index", icon: Folder, show: userRole.value === 'admin', pattern: "admin.deliveries.index" },
]);

const visibleMenuItems = computed(() => rawMenuItems.value.filter(item => item.show));

// 2. Logic Active Index (Reactive)
const activeIndex = computed(() => {
    const url = page.url; 
    return visibleMenuItems.value.findIndex(item => {
        if (Array.isArray(item.pattern)) {
            return item.pattern.some(p => route().current(p));
        }
        return route().current(item.pattern);
    });
});

// 3. Logic Animasi "Flying Dot"
const isMoving = ref(false);
const moveDirection = ref('down'); 
const ITEM_HEIGHT = 50; 
const GAP = 8; 

watch(activeIndex, (newVal, oldVal) => {
    if (newVal !== -1 && oldVal !== -1 && newVal !== oldVal) {
        moveDirection.value = newVal > oldVal ? 'down' : 'up';
        isMoving.value = true;
        
        setTimeout(() => {
            isMoving.value = false;
        }, 300); 
    }
});

// 4. Logic Tooltip via Teleport
const hoveredMenu = ref(null);
const tooltipPos = ref({ top: 0, left: 110 }); // Default left disesuaikan dengan lebar sidebar tertutup + padding

const onHover = (event, label) => {
    if (props.sidebarOpen) return;
    
    // Ambil koordinat elemen yang dihover
    const rect = event.currentTarget.getBoundingClientRect();
    // Set posisi tengah elemen secara vertikal
    tooltipPos.value.top = rect.top + (rect.height / 2);
    hoveredMenu.value = label;
};

const onLeave = () => {
    hoveredMenu.value = null;
};
</script>

<template>
    <div class="relative flex flex-col gap-2">
        
        <div
            class="absolute z-0 bg-gradient-to-r from-white/100 to-white/100 dark:from-primary-600 dark:to-transparent backdrop-blur-md border border-white/40 dark:border-y-white/10 dark:border-l-white/10 dark:border-r-transparent shadow-lg"
            :class="[
                'h-[50px] transform-gpu transition-all duration-300 ease-in-out', 

                activeIndex === -1 
                    ? 'opacity-00 scale-90' 
                    : 'opacity-90',

                sidebarOpen 
                    ? 'w-[calc(100%-16px)] left-4 rounded-l-full rounded-r-none' 
                    : 'w-[50px] left-1/2 -translate-x-1/2 rounded-full',

                isMoving 
                    ? 'scale-[0.4] rounded-full opacity-80'
                    : 'scale-100 opacity-100',
            ]"
            :style="{
                transformOrigin: 'center', 
                transform: sidebarOpen 
                    ? `translateY(${activeIndex * (ITEM_HEIGHT + GAP)}px)` 
                    : `translate(calc(-50%), ${activeIndex * (ITEM_HEIGHT + GAP)}px)`
            }"
        >
        </div>

        <template v-for="(item, index) in visibleMenuItems" :key="index">
            <Link
                :href="route(item.route)"
                @mouseenter="(e) => onHover(e, item.label)"
                @mouseleave="onLeave"
                class="group relative z-10 flex items-center h-[50px] font-medium no-underline transition-all duration-300"
                :class="[
                    index === activeIndex
                        ? 'text-[#0d1b3e] dark:text-white/80 font-bold' 
                        : 'text-slate-400 hover:text-white dark:text-slate-500 dark:hover:text-white/80',

                    index !== activeIndex 
                        ? [
                            'hover:bg-white/10 dark:hover:bg-gray-800',
                            sidebarOpen 
                                ? 'w-[calc(100%-24px)] ml-4 rounded-l-full rounded-r-none' 
                                : 'w-[50px] mx-auto rounded-full justify-center'
                        ]
                        : [
                            sidebarOpen ? 'ml-4' : 'mx-auto justify-center'
                        ]
                ]"
            >
                <div 
                    class="flex items-center justify-center shrink-0 transition-all duration-300 text-gray-400"
                    :class="[
                        index === activeIndex 
                        ? 'scale-110 text-primary-600 dark:text-white/80' 
                        : 'group-hover:scale-110',
                        sidebarOpen ? 'ml-9 mr-3' : 'w-full'
                    ]"
                >
                    <component :is="item.icon" class="w-6 h-6" />
                </div>

                <span 
                    v-show="sidebarOpen" 
                    class="whitespace-nowrap transition-opacity duration-300 delay-75"
                    :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0 overflow-hidden'"
                >
                    {{ item.label }}
                </span>
            </Link>
        </template>
        
        <Teleport to="body">
            <div
                v-if="hoveredMenu && !sidebarOpen"
                class="fixed bg-[#0d1b3e]/90 dark:bg-slate-900/95 backdrop-blur-md text-white text-sm font-bold px-3 py-1.5 rounded-lg pointer-events-none whitespace-nowrap shadow-xl z-[9999] border border-white/20 dark:border-white/10 transition-opacity duration-200"
                :style="{ top: tooltipPos.top + 'px', left: tooltipPos.left + 'px', transform: 'translateY(-50%)' }"
            >
                {{ hoveredMenu }}
                <div class="absolute top-1/2 -left-1 -translate-y-1/2 border-4 border-transparent border-r-[#0d1b3e]/90 dark:border-r-slate-900/95"></div>
            </div>
        </Teleport>

    </div>
</template>

<style scoped>
.relative {
    user-select: none;
}
</style>