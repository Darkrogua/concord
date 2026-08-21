const DB_NAME = 'concord_media_v1'
const STORE_NAME = 'gallery_photos'

function openDatabase() {
  return new Promise((resolve, reject) => {
    if (typeof indexedDB === 'undefined') {
      reject(new Error('IndexedDB is unavailable'))
      return
    }

    const request = indexedDB.open(DB_NAME, 1)
    request.onupgradeneeded = () => {
      const database = request.result
      if (!database.objectStoreNames.contains(STORE_NAME)) {
        database.createObjectStore(STORE_NAME)
      }
    }
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })
}

function runTransaction(mode, action) {
  return openDatabase().then((database) => new Promise((resolve, reject) => {
    const transaction = database.transaction(STORE_NAME, mode)
    const store = transaction.objectStore(STORE_NAME)
    const request = action(store)

    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
    transaction.oncomplete = () => database.close()
    transaction.onerror = () => {
      database.close()
      reject(transaction.error)
    }
  }))
}

export async function saveGalleryPhoto(photoId, file) {
  if (!photoId || !file) {
    return
  }
  const media = {
    bytes: await file.arrayBuffer(),
    type: file.type || 'application/octet-stream',
  }
  return runTransaction('readwrite', (store) => store.put(media, photoId))
}

export function loadGalleryPhoto(photoId) {
  if (!photoId) {
    return Promise.resolve(null)
  }
  return runTransaction('readonly', (store) => store.get(photoId)).then((media) => {
    if (!media?.bytes) {
      return null
    }
    return new Blob([media.bytes], { type: media.type })
  })
}

export function deleteGalleryPhoto(photoId) {
  if (!photoId) {
    return Promise.resolve()
  }
  return runTransaction('readwrite', (store) => store.delete(photoId))
}
