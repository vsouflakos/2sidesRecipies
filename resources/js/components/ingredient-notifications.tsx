import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useState } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as ingredientShow } from '@/routes/ingredients';

interface IngredientNotification {
    id: string;
    data: {
        ingredient_id: number;
        ingredient_name: string;
        decision: 'approved' | 'rejected';
        notes: string | null;
    };
    created_at: string;
}

export function IngredientNotifications() {
    const { t } = useLaravelReactI18n();
    const [isOpen, setIsOpen] = useState(false);

    const ingredientNotifications = (usePage().props.ingredientNotifications as IngredientNotification[] | null) ?? [];

    if (ingredientNotifications.length === 0) {
        return null;
    }

    return (
        <div className="relative px-2">
            <Button
                variant="ghost"
                size="sm"
                className="relative w-full justify-start gap-2"
                onClick={() => setIsOpen((prev) => !prev)}
                aria-label="Ingredient notifications"
            >
                <Bell className="size-4" />
                <span className="text-sm">{t('app.ingredients.notifications_label', { default: 'Notifications' })}</span>
                <Badge variant="destructive" className="ml-auto">
                    {ingredientNotifications.length}
                </Badge>
            </Button>

            {isOpen && (
                <div className="mb-2 flex flex-col gap-2">
                    {ingredientNotifications.map((notification) => (
                        <Link
                            key={notification.id}
                            href={ingredientShow({ ingredient: notification.data.ingredient_id }).url}
                            onClick={() => setIsOpen(false)}
                        >
                            <Alert className="cursor-pointer hover:bg-muted/50 transition-colors">
                                <AlertDescription>
                                    {notification.data.decision === 'approved'
                                        ? t('app.ingredients.notif_approved', {
                                              name: notification.data.ingredient_name,
                                          })
                                        : t('app.ingredients.notif_rejected', {
                                              name: notification.data.ingredient_name,
                                              note: notification.data.notes ?? '',
                                          })}
                                </AlertDescription>
                            </Alert>
                        </Link>
                    ))}
                </div>
            )}
        </div>
    );
}
