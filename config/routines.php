<?php
/**
 * SentinelStack — curated movement / stretch routines (configuration data).
 *
 * Lives in /config because the content is curated copy that does not need to
 * change per user (no PSR-4 class autoload needed). Completion per user/day is
 * stored in the movement_logs table, keyed by routine_key.
 *
 * Shape per routine:
 *   - name        : display title
 *   - target      : one-line body-area description (e.g. "Neck, shoulders, wrists")
 *   - duration    : seconds
 *   - time        : morning | midday | evening | anytime
 *                   When a routine naturally fits in the user's day. The
 *                   /movement page renders a small tag from this.
 *   - icon        : sun | desk | face | floor | standing | moon | core | walk
 *                   Key from the inline SVG set in templates/movement.php.
 *   - description : multi-line copy; the template splits on "\n(?=\d+\.)"
 *                   to render each "1. ..., 2. ..." line as its own paragraph.
 */

return [
    'gentle_wake' => [
        'name'        => 'Gentle Wake-Up',
        'target'      => 'Whole body, low effort',
        'duration'    => 300, // 5 minutes
        'time'        => 'morning',
        'icon'        => 'sun',
        'description' => "Five minutes to wake the spine and shoulders without leaving the bed or floor.\n1. Lying on your back, bring both knees in toward the chest and breathe slowly for 30 seconds.\n2. Extend one leg at a time to the floor, ankle circles in both directions, 20 seconds per side.\n3. Reach both arms overhead on an inhale, let them fall beside you on an exhale. Repeat five times.\n4. Roll to your side and press up to seated, pausing before standing. Notice your breath.\n5. Sit quietly for one minute, hands resting on your thighs, eyes soft.",
    ],

    'desk_reset' => [
        'name'        => 'Desk Reset',
        'target'      => 'Neck, shoulders, wrists',
        'duration'    => 420, // 7 minutes
        'time'        => 'midday',
        'icon'        => 'desk',
        'description' => "A mid-day reset for anyone who has been sitting at a screen for hours.\n1. Sitting tall, drop your right ear toward your right shoulder. Breathe here for 30 seconds, then switch sides.\n2. Roll the shoulders backward in slow circles five times, then forward five times.\n3. Extend your right arm across the chest, gently press it in with the left hand. Switch after 30 seconds.\n4. Shake out the wrists, then make soft fists and open the hands ten times.\n5. Stand. Lift onto tiptoes ten times to wake the calves.\n6. Stand tall, hands at your sides, and take five long breaths through the nose.",
    ],

    'tension_release' => [
        'name'        => 'Tension Release',
        'target'      => 'Jaw, neck, shoulders',
        'duration'    => 360, // 6 minutes
        'time'        => 'midday',
        'icon'        => 'face',
        'description' => "A short sequence for when stress is showing up in the upper body.\n1. Sitting or standing. Inhale through the nose for four counts. Exhale long, softened, for six. Repeat five rounds.\n2. Open the jaw as wide as comfortable, then close. Three slow repetitions.\n3. Press the tongue gently to the roof of the mouth for five seconds, then release. Repeat three times.\n4. Roll the head: chin to chest, right ear to right shoulder, left ear to left shoulder, back to center. Once on each side.\n5. Interlace the fingers behind the back, gently open the chest for 30 seconds.\n6. Shake the hands out above the head for ten seconds, then let everything drop.",
    ],

    'morning_floor_flow' => [
        'name'        => 'Morning Floor Flow',
        'target'      => 'Hip flexors, hamstrings, back',
        'duration'    => 600, // 10 minutes
        'time'        => 'morning',
        'icon'        => 'floor',
        'description' => "A short floor sequence to undo hours of sitting.\n1. Child's pose: knees wide, big toes touching, forehead toward the floor. One minute.\n2. Cat-cow: from hands and knees, inhale arching the back, exhale rounding it. Eight slow rounds.\n3. Downward dog: press hips up, pedal the feet for 30 seconds.\n4. Low lunge: step the right foot forward, left knee down. Hold 30 seconds each side.\n5. Supine twist: on your back, knees to right, arms in a T, gaze left. 30 seconds each side.\n6. Happy baby: holding the outer feet, gently rock. 30 seconds.\n7. Final rest lying flat: one minute of stillness.",
    ],

    'standing_reset' => [
        'name'        => 'Standing Reset',
        'target'      => 'Legs, spine, posture',
        'duration'    => 300, // 5 minutes
        'time'        => 'midday',
        'icon'        => 'standing',
        'description' => "A no-mat routine for any time you can stand for five minutes.\n1. Stand with feet hip-width apart, soft knees. Rock weight forward into the toes, back into the heels.\n2. Side bends: right arm up and over, left arm down. 20 seconds each side.\n3. Standing twists: arms loose, rotate gently from the waist. 30 seconds each side.\n4. Forward fold: hinge at the hips, soft knees, let the head hang. 45 seconds.\n5. Roll up slowly, head last. Stand tall.\n6. March in place for one minute at a comfortable pace.",
    ],

    'evening_wind_down' => [
        'name'        => 'Evening Wind-Down',
        'target'      => 'Whole body, slow',
        'duration'    => 480, // 8 minutes
        'time'        => 'evening',
        'icon'        => 'moon',
        'description' => "A calming sequence to close the day.\n1. Seated cross-legged: three rounds of breath at a four-six tempo (in four, out six).\n2. Seated forward fold: 45 seconds.\n3. Pigeon pose from hands and knees: 45 seconds each side.\n4. Sphinx pose: forearms down, chest lifted, 45 seconds.\n5. Recline with a book under the small of the back, legs long, arms at sides. Two minutes.",
    ],

    'core_ground' => [
        'name'        => 'Core Ground',
        'target'      => 'Deep core, lower back',
        'duration'    => 420, // 7 minutes
        'time'        => 'evening',
        'icon'        => 'core',
        'description' => "Slow, careful core work — no crunches required.\n1. Lying on your back, knees bent, feet flat. Slow diaphragmatic breathing for one minute.\n2. Pelvic tilts: exhale and gently press the low back to the floor. 10 small repetitions.\n3. Dead bug: extend opposite arm and leg, then switch. 6 slow repetitions each side.\n4. Bird dog: from hands and knees, extend opposite arm and leg. 30 seconds each side.\n5. Forearm plank: hold for 30 seconds, longer if it feels good. Stop early if anything pulls.\n6. Knees to chest rest: one minute.",
    ],

    'walking_pause' => [
        'name'        => 'Walking Pause',
        'target'      => 'Mindful movement, low effort',
        'duration'    => 600, // 10 minutes
        'time'        => 'anytime',
        'icon'        => 'walk',
        'description' => "A short walking practice, indoors or out, no special gear.\n1. Stand for 30 seconds and notice what is happening in the body right now.\n2. Begin walking slowly. Match the breath: inhale for three steps, exhale for three.\n3. When the mind wanders (it will), gently return to the count.\n4. After five minutes, stop. Stand with eyes closed for one minute.\n5. Notice one new thing about a familiar place on the return walk.\n6. End by standing still and taking three slow, long breaths.",
    ],
];
