import { Head, Link, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useState } from 'react';
import { toast } from 'sonner';
import { AllergenPanel } from '@/components/ingredients/allergen-panel';
import { NutritionPanel } from '@/components/ingredients/nutrition-panel';
import { PriceForm } from '@/components/ingredients/price-form';
import { PriceHistory } from '@/components/ingredients/price-history';
import { SubmitAction } from '@/components/ingredients/submit-action';
import { VerifyAction } from '@/components/ingredients/verify-action';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    destroy as destroyIngredient,
    edit as editIngredient,
} from '@/actions/App/Http/Controllers/Ingredients/PrivateIngredientController';
import { index as ingredientsIndex } from '@/routes/ingredients';
import type { CanFlags, IngredientDetail, UnitOption } from '@/types/ingredient';

interface IngredientShowProps {
    ingredient: IngredientDetail;
    can: CanFlags;
    units: UnitOption[];
}

export default function IngredientShow({ ingredient, can, units }: IngredientShowProps) {
    const { t } = useLaravelReactI18n();
    const [deleteOpen, setDeleteOpen] = useState(false);

    function handleDelete() {
        router.delete(destroyIngredient(ingredient.id).url, {
            onSuccess: () => {
                toast.success(t('app.ingredients.delete_toast'));
            },
        });
    }

    return (
        <>
            <Head title={ingredient.name} />

            <div className="flex flex-col gap-6 p-4">
                {/* Breadcrumb */}
                {ingredient.category && (
                    <Breadcrumb>
                        <BreadcrumbList>
                            <BreadcrumbItem>
                                <BreadcrumbLink asChild>
                                    <Link href={ingredientsIndex().url}>
                                        {t('app.ingredients.page_title')}
                                    </Link>
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            {ingredient.category.parent && (
                                <>
                                    <BreadcrumbSeparator />
                                    <BreadcrumbItem>
                                        <BreadcrumbPage>{ingredient.category.parent}</BreadcrumbPage>
                                    </BreadcrumbItem>
                                </>
                            )}
                            <BreadcrumbSeparator />
                            <BreadcrumbItem>
                                <BreadcrumbPage>{ingredient.category.name}</BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                )}

                {/* Page header */}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex flex-wrap items-start gap-3">
                        <div>
                            <h1 className="text-[28px] font-semibold leading-[1.2]">
                                {ingredient.name}
                            </h1>
                            {ingredient.submission_status === 'approved' && ingredient.contributed_by && (
                                <p className="text-sm text-muted-foreground mt-1">
                                    {t('app.ingredients.contributed_by', {
                                        name: ingredient.contributed_by,
                                    })}
                                </p>
                            )}
                        </div>
                        {ingredient.verified && (
                            <Badge className="bg-accent text-accent-foreground mt-1">
                                {t('app.ingredients.badge_verified')}
                            </Badge>
                        )}
                    </div>

                    {/* Action buttons area */}
                    <div className="flex flex-wrap items-center gap-2">
                        {/* Verify action for Mod/Admin */}
                        <VerifyAction ingredient={ingredient} can={can} />

                        {/* Submit/Withdraw action for ingredient owner */}
                        <SubmitAction ingredient={ingredient} can={can} />

                        {/* Edit/Delete for private ingredient owner */}
                        {can.manage && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={editIngredient(ingredient.id).url}>
                                        {t('app.ingredients.edit_submit')}
                                    </Link>
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={() => setDeleteOpen(true)}
                                >
                                    {t('app.ingredients.delete_confirm')}
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {/* Frozen banner — shown when ingredient is under review */}
                {ingredient.submission_status === 'submitted' && (
                    <Alert variant="default">
                        <AlertDescription>
                            {t('app.ingredients.frozen_banner')}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Tabs: Nutrition / Allergens / Conversions — Prices slot reserved for Plan 02-06 */}
                <Tabs defaultValue="nutrition">
                    <TabsList className="w-full flex-wrap sm:w-auto">
                        <TabsTrigger value="nutrition">
                            {t('app.ingredients.tab_nutrition')}
                        </TabsTrigger>
                        <TabsTrigger value="allergens">
                            {t('app.ingredients.tab_allergens')}
                        </TabsTrigger>
                        <TabsTrigger value="conversions">
                            {t('app.ingredients.tab_conversions')}
                        </TabsTrigger>
                        <TabsTrigger value="prices">
                            {t('app.ingredients.tab_prices')}
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="nutrition" className="mt-6">
                        <NutritionPanel ingredient={ingredient} />
                    </TabsContent>

                    <TabsContent value="allergens" className="mt-6">
                        <AllergenPanel allergens={ingredient.allergens} />
                    </TabsContent>

                    <TabsContent value="conversions" className="mt-6">
                        {ingredient.conversions.length === 0 ? (
                            <p className="text-[14px] text-muted-foreground">
                                {t('app.ingredients.conversions_empty')}
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('app.ingredients.price_amount_label')}</TableHead>
                                        <TableHead>{t('app.ingredients.price_unit_label')}</TableHead>
                                        <TableHead>Gram weight</TableHead>
                                        <TableHead>Source</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {ingredient.conversions.map((conversion, index) => (
                                        <TableRow key={index}>
                                            <TableCell>{conversion.from_amount}</TableCell>
                                            <TableCell>
                                                {conversion.unit
                                                    ? `${conversion.unit.name} (${conversion.unit.symbol})`
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>{conversion.gram_weight} g</TableCell>
                                            <TableCell>{conversion.source}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </TabsContent>

                    <TabsContent value="prices" className="mt-6">
                        <div className="space-y-8">
                            <PriceForm
                                ingredientId={ingredient.id}
                                units={units}
                            />
                            <PriceHistory prices={ingredient.prices} />
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Delete confirmation dialog */}
            {can.manage && (
                <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {t('app.ingredients.delete_dialog_body', {
                                    name: ingredient.name,
                                })}
                            </DialogTitle>
                            <DialogDescription>
                                {t('app.ingredients.delete_dialog_body', {
                                    name: ingredient.name,
                                })}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="outline">
                                    {t('app.ingredients.delete_cancel')}
                                </Button>
                            </DialogClose>
                            <Button variant="destructive" onClick={handleDelete}>
                                {t('app.ingredients.delete_confirm')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}

IngredientShow.layout = {
    breadcrumbs: [
        {
            title: 'Ingredient Library',
            href: ingredientsIndex().url,
        },
    ],
};
