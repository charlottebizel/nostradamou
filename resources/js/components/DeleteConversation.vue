<script setup>
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const props = defineProps({
    conversation: Object,
});

const form = useForm({});

const submit = () => {
    form.delete(`/chat/${props.conversation.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <button class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity p-1" title="Supprimer la conversation">
                🗑️
            </button>
        </DialogTrigger>
        
        <DialogContent>
            <form @submit.prevent="submit" class="space-y-6">
                <DialogHeader class="space-y-3">
                    <DialogTitle>Êtes-vous sûr de vouloir supprimer cette conversation ?</DialogTitle>
                    <DialogDescription>
                        Cette action est irréversible, toutes les données liées à cette conversation (ainsi que les prédictions de l'Oracle) seront définitivement supprimées.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">Annuler</Button>
                    </DialogClose>
                    <Button type="submit" variant="destructive" :disabled="form.processing">Supprimer</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
