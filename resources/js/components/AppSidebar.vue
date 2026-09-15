<script setup lang="ts">
import { BookOpen, Database, FolderGit2, LayoutGrid, Share2 } from '@lucide/vue'
import { dashboard, dumps, explorer } from '@/routes'
import type { NavItem } from '@/types'

const mainNavItems: NavItem[] = [
  {
    title: 'Dashboard',
    href: dashboard(),
    icon: LayoutGrid,
  },
  {
    title: 'Explorer',
    href: explorer(),
    icon: Share2,
  },
  {
    title: 'Dumps',
    href: dumps(),
    icon: Database,
  },
]

const footerNavItems: NavItem[] = [
  {
    title: 'Repository',
    href: 'https://github.com/danielj247/gaia',
    icon: FolderGit2,
  },
  {
    title: 'Documentation',
    href: 'https://www.opensanctions.org/datasets/',
    icon: BookOpen,
  },
]
</script>

<template>
  <UiSidebar collapsible="icon" variant="inset">
    <UiSidebarHeader>
      <UiSidebarMenu>
        <UiSidebarMenuItem>
          <UiSidebarMenuButton size="lg" as-child>
            <Link :href="dashboard()">
              <AppLogo />
            </Link>
          </UiSidebarMenuButton>
        </UiSidebarMenuItem>
      </UiSidebarMenu>
    </UiSidebarHeader>

    <UiSidebarContent>
      <NavMain :items="mainNavItems" />
    </UiSidebarContent>

    <UiSidebarFooter>
      <NavFooter :items="footerNavItems" />
      <NavUser />
    </UiSidebarFooter>
  </UiSidebar>
  <slot />
</template>
