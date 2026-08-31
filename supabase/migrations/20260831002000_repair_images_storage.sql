-- Store device photos as original files instead of embedding/compressing them in a row.
insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values (
  'repair-images',
  'repair-images',
  true,
  52428800,
  array['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif']
)
on conflict (id) do update set
  public = excluded.public,
  file_size_limit = excluded.file_size_limit,
  allowed_mime_types = excluded.allowed_mime_types;

drop policy if exists "repair_images_public_read" on storage.objects;
create policy "repair_images_public_read"
on storage.objects for select
to anon, authenticated
using (bucket_id = 'repair-images');

drop policy if exists "repair_images_public_insert" on storage.objects;
create policy "repair_images_public_insert"
on storage.objects for insert
to anon, authenticated
with check (bucket_id = 'repair-images');

drop policy if exists "repair_images_public_update" on storage.objects;
create policy "repair_images_public_update"
on storage.objects for update
to anon, authenticated
using (bucket_id = 'repair-images')
with check (bucket_id = 'repair-images');

drop policy if exists "repair_images_public_delete" on storage.objects;
create policy "repair_images_public_delete"
on storage.objects for delete
to anon, authenticated
using (bucket_id = 'repair-images');
