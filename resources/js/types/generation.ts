export type GenerationStatus =
    | 'pending'
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';

export type Generation = {
    id: string;
    status: GenerationStatus;
    waiting_for_slot: boolean;
    retrying: boolean;
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
    estimate: { min: string; expected: string; max: string };
    charge: { display: string; exact: string } | null;
    tokens: {
        input_text: number;
        input_image: number;
        output: number;
        reasoning: number;
    } | null;
    image: string | null;
    error: string | null;
    created_at: string;
    completed_at: string | null;
};

export const IN_FLIGHT: GenerationStatus[] = ['pending', 'queued', 'processing'];

export type CursorPage<T> = {
    data: T[];
    next_page_url: string | null;
    prev_page_url: string | null;
};
