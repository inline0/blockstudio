export const downloadFile = (url: string, fileName?: string): void => {
  const link = document.createElement('a');
  link.href = url;

  if (fileName) {
    link.download = fileName;
  }

  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};
