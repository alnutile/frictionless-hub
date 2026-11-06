import { type WalkthroughActivity } from '@/types';

/**
 * Where a walkthrough step links to: its screen when built, otherwise its "planned" preview.
 */
export function activityHref(activity: WalkthroughActivity, initiativeId: number): string {
    return activity.route ? route(activity.route, initiativeId) : route('initiatives.planned', { initiative: initiativeId, key: activity.key });
}
