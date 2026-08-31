# Supabase Storage Setup for Image Uploads

This guide walks through the complete setup needed to enable image uploads in the CircuitCare repair order app.

## Architecture Overview

- **Frontend**: Static GitHub Pages (no backend server)
- **Database**: Supabase PostgreSQL (repair orders, spare parts)
- **Image Storage**: Supabase Storage (public bucket)
- **Image Flow**: Browser → Supabase Storage → Save URL in DB

This avoids storing huge base64 strings in the database row, which was causing save failures.

---

## Step 1: Create the Storage Bucket in Supabase

### Via Supabase Dashboard

1. Go to https://supabase.com/dashboard
2. Select your project: `vcydonmiwbyqxpndbloq`
3. Click **Storage** in the left sidebar
4. Click **Create bucket**
5. Name: `repair-images` (exactly)
6. Set visibility to **Public**
7. Click **Create**

The bucket must be public so the browser can upload files directly.

---

## Step 2: Add Storage Policies

The browser needs permission to upload files to the bucket.

### Via SQL Editor

1. In Supabase, go to **SQL Editor**
2. Click **New query**
3. Copy and paste the policy SQL below **exactly as shown** (no extra parentheses or modifications):

```sql
create policy "repair_images_select"
on storage.objects
for select
using (bucket_id = 'repair-images');

create policy "repair_images_insert"
on storage.objects
for insert
with check (bucket_id = 'repair-images');

create policy "repair_images_update"
on storage.objects
for update
using (bucket_id = 'repair-images')
with check (bucket_id = 'repair-images');

create policy "repair_images_delete"
on storage.objects
for delete
using (bucket_id = 'repair-images');
```

4. Click **Run** or press Ctrl+Enter
5. Confirm all 4 policies are created

If you get a syntax error, make sure:
- No extra parentheses around the block
- Each policy is separated by a semicolon
- No "using ((create policy ...))" syntax

---

## Step 3: Verify App Configuration

The app is already configured to use the `repair-images` bucket.

In `index.html`, the bucket is set as:

```javascript
const STORAGE_BUCKET = 'repair-images';
```

This must match exactly with the bucket you created. If you named the bucket differently, update this constant.

---

## Step 4: Verify Database Table

The order record stores image URLs in the `images` column.

The app uses:

```javascript
images: JSON.stringify(safeImages)
```

Each image object contains:
- `url`: public URL from Supabase Storage (preferred)
- `data`: fallback compressed base64 (if Storage upload fails)
- `name`: original filename
- `type`: MIME type

This keeps the database row small and reliable.

---

## Step 5: Test Image Upload

### In the App

1. Refresh the app at https://hisyam94-tech.github.io/cc/
2. Create a new repair order
3. Upload **one small photo** (JPG or PNG, < 10MB)
4. Fill in other order details
5. Click **Save Order**

### Expected Result

✅ **Success**:
- Image uploads to Supabase Storage
- Order saves with image URL in database
- Image displays in order preview

❌ **Failure**:
- Check browser console (F12) for error messages
- Confirm bucket exists and is public
- Confirm policies are created
- Confirm bucket name matches app config

---

## Troubleshooting

### Error: "Bucket not found"

- Bucket `repair-images` does not exist
- Solution: Create the bucket in Supabase Storage

### Error: "Permission denied"

- Policies are not set correctly
- Solution: Re-run the SQL policies, one statement at a time

### Error: "The object already exists"

- File with the same name already uploaded
- Solution: Try a different image or delete the old one from Storage

### Image uploads but doesn't save

- The order save failed, but image is in Storage
- Solution: Check Supabase console for order insert errors

### Image preview is blank

- Image URL is not loading
- Solution: Check if the image file is actually in the Storage bucket
- Use browser DevTools → Network tab to verify the URL is accessible

---

## Verification Checklist

- [ ] Bucket `repair-images` exists in Supabase Storage
- [ ] Bucket is set to **Public**
- [ ] 4 Storage policies created successfully
- [ ] App config uses `STORAGE_BUCKET = 'repair-images'`
- [ ] Upload a test image successfully
- [ ] Order saves without "row size" errors
- [ ] Image URL appears in database
- [ ] Image displays in order preview

---

## What the App Does Now

1. User selects image files
2. For each file:
   - Try to upload to Supabase Storage
   - Get public URL from Storage
   - Save URL to images array
3. If Storage upload fails:
   - Fall back to compressed base64 data URL
   - Still save the order (with smaller payload)
4. When saving order:
   - Store image URLs (or base64) as JSON in `images` column
   - Keep row size small and within limits

---

## Why This Works

**Old Approach** (caused failures):
- Large base64 string → Database row → Row too big → Save fails

**New Approach** (reliable):
- File → Storage bucket → Get URL → Store URL → Save succeeds

This is the standard pattern for static frontends (GitHub Pages, Netlify, Vercel) with cloud storage backends.

---

## Support

If you encounter issues:

1. Check the browser console (F12) for exact error messages
2. Verify Supabase project ref: `vcydonmiwbyqxpndbloq`
3. Confirm bucket is public and has correct policies
4. Try uploading a very small image (< 1MB) first
5. Check Storage bucket contents to see if file was uploaded
6. Verify database `repair_orders` table has `images` column

---

**Setup Date**: 2026-08-31  
**App**: CircuitCare Repair Order Management  
**Database**: Supabase PostgreSQL  
**Storage**: Supabase Storage (public bucket)
